<?php
/**
 * Plugin Name: SMTP Test
 * Description: Sends weekly test emails from child sites to a parent site and verifies delivery.
 * Version: 1.2.2
 * Author: James Welbes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants.
define('SMTP_TEST_VERSION', '1.2.2');
define('SMTP_TEST_PATH', plugin_dir_path(__FILE__));
define('SMTP_TEST_URL', plugin_dir_url(__FILE__));
define('SMTP_TEST_MIN_WP_VERSION', '5.8');
define('SMTP_TEST_MIN_PHP_VERSION', '7.4');

class SMTP_Test_Plugin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'maybe_send_manual_test_email' ] );
        add_action( 'admin_post_smtp_test_reset', [ $this, 'reset_plugin_data' ] );

        if ( get_option( 'smtp_test_site_type' ) === 'child' ) {
            add_action( 'smtp_test_daily_check', [ $this, 'maybe_send_weekly_email' ] );

            // Schedule daily check at 00:01 if not already scheduled
            if ( ! wp_next_scheduled( 'smtp_test_daily_check' ) ) {
                $timezone = wp_timezone(); // WordPress timezone (DateTimeZone object)
                $datetime = new DateTime( 'tomorrow 00:03:00', $timezone );
                $timestamp = $datetime->getTimestamp(); // This is in local time
                
        
                wp_schedule_event( $timestamp, 'daily', 'smtp_test_daily_check' );
            }
        }

        if ( get_option( 'smtp_test_site_type' ) === 'parent' ) {
            add_shortcode( 'check_email_token', [ $this, 'check_email_token' ] );
        }
        
    }

    public function reset_plugin_data() {
        // Security check
        if (
            ! current_user_can( 'manage_options' ) ||
            ! isset( $_POST['smtp_test_reset_nonce'] ) ||
            ! wp_verify_nonce( $_POST['smtp_test_reset_nonce'], 'smtp_test_reset_action' )
        ) {
            wp_die( 'Unauthorized request' );
        }
    
        // Clear crons
        wp_clear_scheduled_hook( 'smtp_test_daily_check' );
        
    
        // Delete options
        delete_option( 'smtp_test_site_type' );
        delete_option( 'smtp_test_email_to' );
        delete_option( 'smtp_test_day' );
        delete_option( 'smtp_test_app_password' );
        delete_option( 'smtp_test_child_sites' );
    
        // Optional: Delete transients from previous email runs
        global $wpdb;
        $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_smtp_test_email_sent_%'" );
    
        // Redirect with success message
        wp_redirect( admin_url( 'options-general.php?page=smtp-test&reset=1' ) );
        exit;
    }
    

    public function register_settings_page() {
        add_options_page( 'SMTP Test Settings', 'SMTP Test', 'manage_options', 'smtp-test', [ $this, 'settings_page' ] );
    }

    public function register_settings() {
        register_setting( 'smtp_test_settings', 'smtp_test_site_type' );
        register_setting( 'smtp_test_settings', 'smtp_test_email_to' );
        register_setting( 'smtp_test_settings', 'smtp_test_day', [
            'default' => 'Friday'
        ] );
        register_setting( 'smtp_test_settings', 'smtp_test_app_password', [
            'sanitize_callback' => [ $this, 'encrypt_password' ]
        ] );
        register_setting( 'smtp_test_settings', 'smtp_test_child_sites' );
    }

    public function encrypt_password( $password ) {
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
        $site_type = get_option('smtp_test_site_type');
        $site_name = sanitize_title( get_bloginfo( 'name' ) );
        $days = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
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
                                <option value="child" <?php selected( $site_type, 'child' ); ?>>Child Site</option>
                                <option value="parent" <?php selected( $site_type, 'parent' ); ?>>Parent Site</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Send Test Emails To</th>
                        <td><input type="email" name="smtp_test_email_to" value="<?php echo esc_attr( get_option('smtp_test_email_to') ); ?>" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Test Day</th>
                        <td>
                            <select name="smtp_test_day">
                                <?php foreach ( $days as $day ) : ?>
                                    <option value="<?php echo esc_attr( $day ); ?>" <?php selected( get_option('smtp_test_day', 'Friday'), $day ); ?>><?php echo esc_html( $day ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Test emails are only sent if today matches the selected day.</p>
                        </td>
                    </tr>

                    <?php if ( $site_type === 'parent' ) : ?>
                        <tr valign="top">
                            <th scope="row">Gmail App Password</th>
                            <td>
                                <?php $encrypted = get_option('smtp_test_app_password'); $has_password = ! empty( $encrypted ); ?>
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

                    <?php if ( $site_type === 'child' ) : ?>
                        <tr valign="top">
                            <th scope="row">Your Site Token</th>
                            <td><code><?php echo esc_html( $site_name ); ?></code></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Send Test Email</th>
                            <td>
                                <?php submit_button('Send Test Email Now', 'secondary', 'smtp_test_send_manual', false); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    
                    <tr valign="top">
                        <th scope="row">Cron Note</th>
                        <td><p>⏱️ WordPress cron only runs when someone visits your site. For low-traffic sites, the test email may be delayed until a visit triggers the cron.</p></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <hr>
                <h2>Reset Plugin</h2>
                <?php if ( isset( $_GET['reset'] ) && $_GET['reset'] == 1 ) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p>✅ Plugin settings and scheduled tasks have been reset.</p>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'smtp_test_reset_action', 'smtp_test_reset_nonce' ); ?>
                    <input type="hidden" name="action" value="smtp_test_reset">
                    <?php submit_button( 'Reset Plugin', 'delete', 'submit', false ); ?>
                </form>
            <?php endif; ?>

        </div>
        <?php
    }

    public function maybe_send_manual_test_email() {
        if (
            isset( $_POST['smtp_test_send_manual'] ) &&
            check_admin_referer( 'smtp_test_settings-options' )
        ) {
            $this->send_test_email();
            exit;
        }
    }
    

    public function maybe_send_weekly_email() {
        // Only run from cron
        if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
            return;
        }
    
        // Get current day in WordPress timezone
        $today = current_time( 'l' );
        $target_day = get_option( 'smtp_test_day', 'Friday' );
    
        if ( $today !== $target_day ) {
            return;
        }
    
        $transient_key = 'smtp_test_email_sent_' . date( 'Y-m-d' );
    
        // Prevent duplicate sends
        if ( get_transient( $transient_key ) ) {
            return;
        }
    
        $this->send_test_email();
    
        // Set transient to mark that we've already sent it today
        set_transient( $transient_key, true, DAY_IN_SECONDS );
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

        if ( defined('DOING_CRON') && DOING_CRON ) return; // don't redirect during cron

        wp_redirect( admin_url( 'options-general.php?page=smtp-test&email_sent=' . ($sent ? '1' : '0') ) );
        exit;
    }

    public function check_email_token() {
        $mailbox = '{imap.gmail.com:993/imap/ssl}INBOX';
        $username = sanitize_email( get_option( 'smtp_test_email_to' ) );
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

function smtp_test_bootstrap() {
    new SMTP_Test_Plugin();

    if ( file_exists( SMTP_TEST_PATH . 'github-update.php' ) ) {
        include_once SMTP_TEST_PATH . 'github-update.php';
    } else {
        error_log( 'github-update.php not found in ' . SMTP_TEST_PATH );
    }
}
smtp_test_bootstrap();

