<?php
/**
 * Plugin Name: SMTP Test
 * Description: Sends weekly test emails from child sites to a parent site and verifies delivery.
 * Version: 1.3.0
 * Author: James Welbes
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Define plugin constants
define( 'SMTP_TEST_VERSION', '1.3.0' );
define( 'SMTP_TEST_PATH', plugin_dir_path( __FILE__ ) );
define( 'SMTP_TEST_URL', plugin_dir_url( __FILE__ ) );
define( 'SMTP_TEST_MIN_WP_VERSION', '5.8' );
define( 'SMTP_TEST_MIN_PHP_VERSION', '7.4' );

// Include necessary files
require_once SMTP_TEST_PATH . 'includes/functions.php';
require_once SMTP_TEST_PATH . 'includes/class-smtp-test.php';
require_once SMTP_TEST_PATH . 'includes/settings-page.php';
require_once SMTP_TEST_PATH . 'includes/tools-page.php';

// Initialize plugin
add_action( 'plugins_loaded', function() {
    new SMTP_Test_Plugin();

    if ( file_exists( SMTP_TEST_PATH . 'github-update.php' ) ) {
        include_once SMTP_TEST_PATH . 'github-update.php';
    }
});