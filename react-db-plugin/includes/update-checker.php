<?php
// Simple GitHub-based update checker for React DB Plugin.
// Fetches latest release info from GitHub and injects it into WordPress update API.

add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin = plugin_basename(__DIR__ . '/../react-db-plugin.php');
    $current = $transient->checked[$plugin] ?? null;
    if (!$current) {
        return $transient;
    }

    $response = wp_remote_get('https://api.github.com/repos/YashubuStudio/react-db-plugin/releases/latest');
    if (is_wp_error($response)) {
        return $transient;
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    if (isset($data->tag_name)) {
        $latest = ltrim($data->tag_name, 'v');
        if (version_compare($latest, $current, '>')) {
            $package = $data->zipball_url ?? '';
            $transient->response[$plugin] = (object) [
                'slug' => 'react-db-plugin',
                'plugin' => $plugin,
                'new_version' => $latest,
                'url' => $data->html_url ?? '',
                'package' => $package,
            ];
        }
    }
    return $transient;
});

add_filter('plugins_api', function($result, $action, $args) {
    if ($action !== 'plugin_information' || $args->slug !== 'react-db-plugin') {
        return $result;
    }

    $response = wp_remote_get('https://api.github.com/repos/YashubuStudio/react-db-plugin/releases/latest');
    if (is_wp_error($response)) {
        return $result;
    }

    $data = json_decode(wp_remote_retrieve_body($response));
    if (!isset($data->tag_name)) {
        return $result;
    }

    $latest = ltrim($data->tag_name, 'v');
    return (object) [
        'name'          => 'React DB Plugin',
        'slug'          => 'react-db-plugin',
        'version'       => $latest,
        'author'        => '<a href="https://github.com/YashubuStudio">YashubuStudio</a>',
        'homepage'      => $data->html_url ?? '',
        'download_link' => $data->zipball_url ?? '',
        'sections'      => [
            'description' => $data->body ?? '',
        ],
    ];
}, 10, 3);

// Enable automatic updates for this plugin by default.
add_filter('auto_update_plugin', function($update, $item) {
    $plugin = plugin_basename(__DIR__ . '/../react-db-plugin.php');
    if (isset($item->plugin) && $item->plugin === $plugin) {
        return true;
    }
    return $update;
}, 10, 2);
