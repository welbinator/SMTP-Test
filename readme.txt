=== SMTP Test ===
Contributors: jameswelbes  
Tags: smtp, email, test email, imap, cron, deliverability  
Requires at least: 5.8  
Tested up to: 6.5  
Requires PHP: 8.1  
Stable tag: 1.3.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Sends weekly test emails from WordPress child sites to a parent site and verifies deliverability via IMAP.

== Description ==

SMTP Test helps WordPress site owners monitor and verify email deliverability by sending automated test emails from multiple child sites to a centralized parent site. It checks for incoming test messages using IMAP and reports the last successful delivery date per child site.

### Features

- Sends scheduled test emails from child sites via CRON.
- Checks parent inbox via IMAP for test tokens.
- Tracks last successful email delivery for each child site.
- Fully AJAX-powered dashboard widget and shortcode output.
- Customizable test schedule and lookback window.
- Works with Gmail (App Password required).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/smtp-test` directory or install via the WordPress plugin admin on your parent site, and all your children sites.
2. Activate the plugin.
3. On each **child site**, choose "Child Site" as the site type and set the test day, timezone and enter the test email address.
4. On the **parent site**, choose "Parent Site" and enter:
    - The test inbox email address.
    - The app password for IMAP access (for Gmail, create one at https://myaccount.google.com/apppasswords).
    - The tokens (slugified site names from each child site. This can be found in the child site settings).
5. Use the `[check_email_token]` shortcode on a page or monitor results from the WordPress dashboard widget.

== Frequently Asked Questions ==

= Does this plugin send real emails? =  
Yes. It sends actual emails using your site's configured SMTP setup, just like your transactional emails.

= What happens if a test email doesn't arrive? =  
The parent site will indicate the last time it successfully received an email from each child site.


== Changelog ==

= 1.3.2 – 2025-07-22 =
* Improved Email Token Reporting: Replaced "Not Found" token checks with dynamic "Last Successful Test" results, displaying the date of the most recent matching email.
* Lookback Range Setting: Added admin setting to customize how many days back to search for emails (default: 14 days).
* Async Dashboard Widget: Converted the dashboard widget to use AJAX, eliminating page load delays caused by IMAP queries.
* Async Shortcode Rendering: Shortcode `[check_email_token]` now loads asynchronously via AJAX, improving frontend page performance.
* Editor Compatibility Fix: Prevented email-checking logic from running in the block/page editor to avoid save and preview issues.
* Frontend Styling: Added support for plugin-specific CSS via `assets/css/styles.css`.
* Refactored jQuery to Vanilla JS: Rewrote all AJAX logic to use modern `fetch()` API for cleaner and lighter frontend JavaScript.

== Upgrade Notice ==

= 1.3.2 =
Major improvements to performance and usability — asynchronous shortcode/widget rendering, lookback range settings, and editor compatibility fixes.
