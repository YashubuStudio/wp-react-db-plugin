<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/includes/cleanup.php';

// Deleting the plugin from WordPress is the explicit full-reset operation.
reactdb_uninstall_current_site();
