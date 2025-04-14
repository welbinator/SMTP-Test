<?php
class SMTP_Test_Plugin {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu_pages' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_smtp_test_reset', [ $this, 'reset_plugin_data' ] );
        add_action( 'admin_post_smtp_test_manual_send', [ $this, 'handle_manual_send' ] );


        if ( get_option( 'smtp_test_site_type' ) === 'child' ) {
            add_action( 'smtp_test_daily_check', [ $this, 'maybe_send_weekly_email' ] );

            if ( ! wp_next_scheduled( 'smtp_test_daily_check' ) ) {
                $timezone = wp_timezone();
                $datetime = new DateTime( '00:03:00', $timezone );
                $timestamp = $datetime->getTimestamp();
                wp_schedule_event( $timestamp, 'daily', 'smtp_test_daily_check' );
            }
        }

        if ( get_option( 'smtp_test_site_type' ) === 'parent' ) {
            add_shortcode( 'check_email_token', [ $this, 'check_email_token' ] );
            add_action( 'wp_dashboard_setup', [ $this, 'add_dashboard_widget' ] );
        }
    }

    public function handle_manual_send() {
        if (
            ! current_user_can( 'manage_options' ) ||
            ! isset( $_POST['smtp_test_manual_nonce'] ) ||
            ! wp_verify_nonce( $_POST['smtp_test_manual_nonce'], 'smtp_test_manual_send_action' )
        ) {
            wp_die( 'Unauthorized request' );
        }
    
        $sent = smtp_test_send_email();
    
        // Correct redirect:
        wp_redirect( admin_url( 'admin.php?page=smtp-test-tools&email_sent=' . ( $sent ? '1' : '0' ) ) );
        exit;
    }
    
    public function register_menu_pages() {
        add_menu_page( 'SMTP Test', 'SMTP Test', 'manage_options', 'smtp-test', 'smtp_test_render_settings_page', 'dashicons-email-alt' );
        add_submenu_page( 'smtp-test', 'Tools', 'Tools', 'manage_options', 'smtp-test-tools', 'smtp_test_render_tools_page' );
    }

    public function register_settings() {
        register_setting( 'smtp_test_settings', 'smtp_test_site_type' );
        register_setting( 'smtp_test_settings', 'smtp_test_email_to' );
        register_setting( 'smtp_test_settings', 'smtp_test_day', [ 'default' => 'Friday' ] );
        register_setting( 'smtp_test_settings', 'smtp_test_app_password', [ 'sanitize_callback' => 'smtp_test_encrypt_password' ] );
        register_setting( 'smtp_test_settings', 'smtp_test_child_sites' );
    }

    public function reset_plugin_data() {
        if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'smtp_test_reset_action', 'smtp_test_reset_nonce' ) ) {
            wp_die( 'Unauthorized request' );
        }

        wp_clear_scheduled_hook( 'smtp_test_daily_check' );
        delete_option( 'smtp_test_site_type' );
        delete_option( 'smtp_test_email_to' );
        delete_option( 'smtp_test_day' );
        delete_option( 'smtp_test_app_password' );
        delete_option( 'smtp_test_child_sites' );

        global $wpdb;
        $wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_smtp_test_email_sent_%'" );

        wp_redirect( admin_url( 'admin.php?page=smtp-test-tools&reset=1' ) );
        exit;
    }

    public function maybe_send_weekly_email() {
        // error_log("let's maybe send an email!");
        if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) return;

        $today = current_time( 'l' );
        $target_day = get_option( 'smtp_test_day', 'Friday' );

        // if ( $today !== $target_day ) {
        //     error_log("[SMTP Test] Today is not your day: Today is $today, target is $target_day.");
        //     return;
        // }

        // error_log("[SMTP Test] Today's the day! Sending test email...");

        if ( $today !== $target_day ) return;

        $transient_key = 'smtp_test_email_sent_' . date( 'Y-m-d' );

        if ( get_transient( $transient_key ) ) {
            // error_log("[SMTP Test] Email already sent today. Skipping.");
            return;
        }

        smtp_test_send_email();
        set_transient( $transient_key, true, DAY_IN_SECONDS );
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget( 'smtp_test_widget', 'SMTP Test Results', function() {
            echo do_shortcode('[check_email_token]');
        });
    }

    public function check_email_token() {
        return smtp_test_check_email_token();
    }
}