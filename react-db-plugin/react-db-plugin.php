<?php
/*
Plugin Name: React DB Plugin
Description: React-based DB management.
Version: 1.0
Author: YourName
*/

defined('ABSPATH') || exit;

add_action('admin_menu', function() {
    add_menu_page(
        'React DB App',
        'React DB',
        'manage_options',
        'react-db-plugin',
        function() {
            echo '<div id="react-db-root"></div>';
            wp_enqueue_script(
                'react-db-plugin-script',
                plugins_url('/assets/app.js', __FILE__),
                [],
                '1.0',
                true
            );
            wp_enqueue_style(
                'react-db-plugin-style',
                plugins_url('/assets/app.css', __FILE__),
                [],
                '1.0'
            );
            wp_localize_script('react-db-plugin-script', 'ReactDbGlobals', [
                'isPlugin' => true
            ]);
        }
    );
});

// REST APIを含める
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/csv-handler.php';
require_once __DIR__ . '/includes/log-handler.php';
require_once __DIR__ . '/includes/shortcode.php';

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
});
