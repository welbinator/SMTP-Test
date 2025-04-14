<?php 

namespace SMTPTest\GitHubUpdater;

function my_plugin_check_for_updates($transient) {
    // error_log("check for updates called");

    $owner = 'welbinator';
    $repo = 'SMTP-Test';

    if (empty($transient->checked)) {
        return $transient;
    }

    // Fetch the latest release from GitHub
    $api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";
    $response = wp_remote_get($api_url, [
        'headers' => ['User-Agent' => 'WordPress']
    ]);

    if (is_wp_error($response)) {
        error_log('GitHub API Error: ' . $response->get_error_message());
        return $transient;
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($release['tag_name'], $release['assets'][0]['browser_download_url'])) {
        error_log('No valid release data or assets found.');
        return $transient;
    }

    $latest_version = ltrim($release['tag_name'], 'v'); // Remove "v" prefix if present
    $download_url = $release['assets'][0]['browser_download_url'];

    // Get the current version of the installed plugin
    $plugin_slug = 'smtp-test/smtp-test.php';
    $current_version = $transient->checked[$plugin_slug] ?? null;
    // error_log('Current version: ' . ($current_version ?? 'unknown'));


    // Skip adding update if current version equals latest version
    if ($current_version && version_compare($latest_version, $current_version, '<=')) {
        // error_log("Current version ($current_version) is up to date.");
        return $transient;
    }

    // Add update data to the transient
    $transient->response[$plugin_slug] = (object) [
        'slug'        => $plugin_slug,
        'plugin'      => $plugin_slug,
        'new_version' => $latest_version,
        'package'     => $download_url,
        'url'         => $release['html_url'],
        'tested'      => get_bloginfo('version'),
        'requires'    => SMTP_TEST_MIN_WP_VERSION,
    ];

    // error_log("Update available: $latest_version");
    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', __NAMESPACE__ . '\\my_plugin_check_for_updates');


function github_plugin_updater_user_agent($args) {
    $args['user-agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . home_url();
    return $args;
}
add_filter('http_request_args', __NAMESPACE__ . '\\github_plugin_updater_user_agent', 10, 1);