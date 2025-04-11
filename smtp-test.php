<?php
/**
 * Plugin Name: SMTP Test
 * Description: Sends weekly test emails from child sites to a parent site and verifies delivery.
 * Version: 1.0.0
 * Author: James Welbes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Define encryption key constant (you should add this to wp-config.php instead)
define( 'SMTP_TEST_ENCRYPTION_KEY', 'your-super-secret-key' );

class SMTP_Test_Plugin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        if ( get_option( 'smtp_test_site_type' ) === 'child' ) {
            add_action( 'admin_post_send_test_email', [ $this, 'send_test_email' ] );
            add_action( 'smtp_test_weekly_cron', [ $this, 'send_test_email' ] );
            if ( ! wp_next_scheduled( 'smtp_test_weekly_cron' ) ) {
                wp_schedule_event( strtotime( 'next friday 6am' ), 'weekly', 'smtp_test_weekly_cron' );
            }
        }

        if ( get_option( 'smtp_test_site_type' ) === 'parent' ) {
            add_shortcode( 'check_email_token', [ $this, 'check_email_token' ] );
        }
    }

    public function register_settings_page() {
        add_options_page( 'SMTP Test Settings', 'SMTP Test', 'manage_options', 'smtp-test', [ $this, 'settings_page' ] );
    }

    public function register_settings() {
        register_setting( 'smtp_test_settings', 'smtp_test_site_type' );
        register_setting( 'smtp_test_settings', 'smtp_test_email_to' );
        register_setting( 'smtp_test_settings', 'smtp_test_app_password', [
            'sanitize_callback' => [ $this, 'encrypt_password' ]
        ] );
    }

    public function encrypt_password( $password ) {
        return openssl_encrypt( $password, 'aes-256-cbc', SMTP_TEST_ENCRYPTION_KEY );
    }

    public function decrypt_password( $encrypted ) {
        return openssl_decrypt( $encrypted, 'aes-256-cbc', SMTP_TEST_ENCRYPTION_KEY );
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>SMTP Test Settings</h1>

            <?php if ( isset($_GET['email_sent']) && $_GET['email_sent'] === '1' ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ Test email sent successfully!</p>
                </div>
            <?php elseif ( isset($_GET['email_sent']) && $_GET['email_sent'] === '0' ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p>❌ Failed to send test email.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'smtp_test_settings' ); ?>
                <?php do_settings_sections( 'smtp_test_settings' ); ?>

                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Site Type</th>
                        <td>
                            <select name="smtp_test_site_type">
                                <option value="child" <?php selected( get_option('smtp_test_site_type'), 'child' ); ?>>Child Site</option>
                                <option value="parent" <?php selected( get_option('smtp_test_site_type'), 'parent' ); ?>>Parent Site</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Send Test Emails To</th>
                        <td><input type="email" name="smtp_test_email_to" value="<?php echo esc_attr( get_option('smtp_test_email_to') ); ?>" /></td>
                    </tr>
                    <?php if ( get_option('smtp_test_site_type') === 'parent' ) : ?>
                    <tr valign="top">
                        <th scope="row">Gmail App Password</th>
                        <td><input type="password" name="smtp_test_app_password" value="" placeholder="Only needed for parent site" /></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if ( get_option('smtp_test_site_type') === 'child' ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <input type="hidden" name="action" value="send_test_email">
                    <?php submit_button('Send Test Email Now'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function send_test_email() {
        $site_name = sanitize_title( get_bloginfo( 'name' ) );
        $date = strtolower( date( 'F-j' ) );
        $token = $site_name . '-' . $date;

        $to = get_option( 'smtp_test_email_to' );
        $subject = 'SMTP Test Email - Token: ' . $token;
        $body = "This is a scheduled test email from $site_name.\n\nToken: $token";
        $headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

        $sent = wp_mail( $to, $subject, $body, $headers );

        wp_redirect( admin_url( 'options-general.php?page=smtp-test&email_sent=' . ($sent ? '1' : '0') ) );
        exit;
    }

    public function check_email_token() {
        $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
        $username = get_option( 'smtp_test_email_to' );
        $password = $this->decrypt_password( get_option( 'smtp_test_app_password' ) );

        $expected_token = sanitize_title( get_bloginfo( 'name' ) ) . '-' . strtolower( date( 'F-j' ) );
        $token_found = false;
        $output = '';

        $inbox = @imap_open( $mailbox, $username, $password );
        if ( ! $inbox ) {
            return '<p style="color:red;">❌ IMAP connection failed: ' . imap_last_error() . '</p>';
        }

        $emails = imap_search( $inbox, 'SINCE "' . date( 'd-M-Y', strtotime('-7 days') ) . '"' );
        if ( ! $emails ) {
            $output .= '<p>📭 No emails found in the past 7 days.</p>';
        } else {
            rsort( $emails );
            foreach ( $emails as $email_number ) {
                $overview = imap_fetch_overview( $inbox, $email_number, 0 );
                $subject = isset( $overview[0]->subject ) ? $overview[0]->subject : '';
                $body = imap_fetchbody( $inbox, $email_number, 1 );

                if ( stripos( $subject, $expected_token ) !== false || stripos( $body, $expected_token ) !== false ) {
                    $token_found = true;
                    break;
                }
            }
        }

        imap_close( $inbox );

        if ( $token_found ) {
            $output .= '<p style="color:green;">✅ Token FOUND: ' . esc_html( $expected_token ) . '</p>';
        } else {
            $output .= '<p style="color:red;">❌ Token NOT FOUND: ' . esc_html( $expected_token ) . '</p>';
        }

        return $output;
    }
}

new SMTP_Test_Plugin();
