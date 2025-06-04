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
            echo '<div id="root"></div>';
            wp_enqueue_script(
                'react-db-plugin-script',
                plugins_url('assets/app.js', __FILE__),
                [],
                '1.0',
                true
            );
            wp_enqueue_style(
                'react-db-plugin-style',
                plugins_url('assets/app.css', __FILE__),
                [],
                '1.0'
            );
            $user = wp_get_current_user();
            wp_localize_script('react-db-plugin-script', 'ReactDbGlobals', [
                'isPlugin'    => true,
                'currentUser' => $user->display_name,
                'logoutUrl'   => wp_logout_url()
            ]);
            wp_add_inline_script(
                'react-db-plugin-script',
                "function reactdb_fix(){if(location.hash==='#/db'){location.hash='#/';}}window.addEventListener('hashchange',reactdb_fix);reactdb_fix();",
                'after'
            );
        }
    );
});


// REST APIを含める
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/csv-handler.php';
require_once __DIR__ . '/includes/log-handler.php';
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
