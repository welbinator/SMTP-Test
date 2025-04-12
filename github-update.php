<?php 

function my_plugin_check_for_updates($transient) {
    // Your GitHub username and repository name
    $owner = 'welbinator';
    $repo = 'SMTP-Test';

    // Only proceed if this is a plugin update check
    if (empty($transient->checked)) {
        return $transient;
    }

    // GitHub API URL to get the latest release
    $api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";

    // Fetch the latest release information
    $response = wp_remote_get($api_url, ['headers' => ['User-Agent' => 'WordPress']]);
    if (is_wp_error($response)) {
        return $transient; // Return early if there's an error
    }

    $release = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($release['tag_name']) && isset($release['assets'][0]['browser_download_url'])) {
        $latest_version = ltrim($release['tag_name'], 'v'); // Remove "v" prefix if present
        $download_url = $release['assets'][0]['browser_download_url'];

        // Plugin's current version from its header
        $plugin_data = get_plugin_data(__FILE__);
        $current_version = $plugin_data['Version'];

        // Check if a new version is available
        if (version_compare($latest_version, $current_version, '>')) {
            $plugin_slug = plugin_basename(__FILE__);

            $transient->response[$plugin_slug] = (object) [
                'slug' => $plugin_slug,
                'new_version' => $latest_version,
                'package' => $download_url,
                'url' => $release['html_url'], // Link to the release page
            ];
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'my_plugin_check_for_updates');

function github_plugin_updater_user_agent($args) {
    $args['user-agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . home_url();
    return $args;
}
add_filter('http_request_args', 'github_plugin_updater_user_agent', 10, 1);