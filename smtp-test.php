<?php
/**
 * Plugin Name: SMTP Test
 * Description: Sends weekly test emails from child sites to a parent site and verifies delivery.
 * Version: 1.0.0
 * Author: James Welbes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

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
        register_setting( 'smtp_test_settings', 'smtp_test_child_sites' );
    }

    public function encrypt_password( $password ) {
        // If field is empty, return the previously saved value (do not overwrite)
        if ( empty( $password ) ) {
            return get_option( 'smtp_test_app_password' );
        }
    
        $key = AUTH_KEY;
        return base64_encode( openssl_encrypt( $password, 'aes-256-cbc', $key, 0, substr( hash( 'sha256', $key ), 0, 16 ) ) );
    }

    public function decrypt_password( $encrypted ) {
        $key = AUTH_KEY;
        return openssl_decrypt( base64_decode( $encrypted ), 'aes-256-cbc', $key, 0, substr( hash( 'sha256', $key ), 0, 16 ) );
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
                        <td><?php 
                            $encrypted = get_option('smtp_test_app_password');
                            $has_password = ! empty( $encrypted );
                            $decrypted_password = $has_password ? $this->decrypt_password( $encrypted ) : '';
                            ?>
                            <input type="password" name="smtp_test_app_password" value="" placeholder="Only needed for parent site" />
                            <?php if ( $has_password ) : ?>
                                <p><em>🔒 A password is saved. Leave blank to keep it.</em></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Child Site Tokens</th>
                        <td>
                            <textarea name="smtp_test_child_sites" rows="5" cols="40" placeholder="One token per line (e.g. roadmapwp, west-side-sewing)"><?php echo esc_textarea( get_option('smtp_test_child_sites') ); ?></textarea>
                            <p class="description">Enter one token per line. Tokens should match the slugified site name from the child site.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        
                    <?php if ( get_option('smtp_test_site_type') === 'child' ) : 
                $site_name = sanitize_title( get_bloginfo( 'name' ) ); ?>
                <th>Your Site Token</th>
                <td><code><?php echo esc_html( $site_name ); ?></code></td>
                    </tr>
                    <tr>
                        <th> Send Test Email</th>
                        <td>
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                    <input type="hidden" name="action" value="send_test_email">
                    <?php submit_button('Send Test Email Now'); ?>
                </form>
                    </td>
                    </tr>
            <?php endif; ?>
                    
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            
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

        $child_sites_raw = get_option( 'smtp_test_child_sites' );
        $child_sites = array_filter( array_map( 'trim', explode( "\n", $child_sites_raw ) ) );

        $output = '';

        $inbox = @imap_open( $mailbox, $username, $password );
        if ( ! $inbox ) {
            return '<p style="color:red;">❌ IMAP connection failed: ' . imap_last_error() . '</p>';
        }

        $emails = imap_search( $inbox, 'SINCE "' . date( 'd-M-Y', strtotime('-7 days') ) . '"' );
        $all_messages = [];

        if ( $emails ) {
            rsort( $emails );
            foreach ( $emails as $email_number ) {
                $overview = imap_fetch_overview( $inbox, $email_number, 0 );
                $subject = isset( $overview[0]->subject ) ? $overview[0]->subject : '';
                $body = imap_fetchbody( $inbox, $email_number, 1 );
                $all_messages[] = $subject . ' ' . $body;
            }
        }

        imap_close( $inbox );

        $output .= '<h2>📬 Token Check Results</h2><ul>';

        foreach ( $child_sites as $token_base ) {
            $expected_token = $token_base . '-' . strtolower( date( 'F-j' ) );
            $found = false;

            foreach ( $all_messages as $content ) {
                if ( stripos( $content, $expected_token ) !== false ) {
                    $found = true;
                    break;
                }
            }

            $output .= '<li>' . esc_html( $token_base ) . ': ' . ( $found ? '<span style="color:green;">✅ Found</span>' : '<span style="color:red;">❌ Not Found</span>' ) . '</li>';
        }

        $output .= '</ul>';

        return $output;
    }
}

new SMTP_Test_Plugin();
