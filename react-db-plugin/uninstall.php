<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

global $wpdb;
$table = $wpdb->prefix . 'reactdb_logs';
$wpdb->query("DROP TABLE IF EXISTS $table");

// Remove the React DB page created on activation
$page = get_page_by_path('react-db-app');
if ($page) {
    wp_delete_post($page->ID, true);
    flush_rewrite_rules();
}
