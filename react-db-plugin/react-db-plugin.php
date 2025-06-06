<?php
/*
Plugin Name: React DB Plugin
Description: React-based DB management.
Version: 1.1
Author: YashubuStudio
*/

defined('ABSPATH') || exit;

// Admin menu entry removed -- the React app is now accessed via a dedicated page


// REST APIを含める
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/csv-handler.php';
require_once __DIR__ . '/includes/log-handler.php';
require_once __DIR__ . '/includes/output-handler.php';
require_once __DIR__ . '/includes/shortcode.php';
require_once __DIR__ . '/includes/block.php';

register_activation_hook(__FILE__, function() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'reactdb_logs';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        action text NOT NULL,
        description text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create front-end page for the React app if it doesn't exist
    if (!get_page_by_path('react-db-app')) {
        wp_insert_post([
            'post_title'   => 'React DB App',
            'post_name'    => 'react-db-app',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '[reactdb_app]'
        ]);
        flush_rewrite_rules();
    }
});

// Use blank template for the React DB page
add_filter('template_include', function ($template) {
    if (is_page('react-db-app')) {
        $custom = plugin_dir_path(__FILE__) . 'templates/blank.php';
        if (file_exists($custom)) {
            return $custom;
        }
    }
    return $template;
});
