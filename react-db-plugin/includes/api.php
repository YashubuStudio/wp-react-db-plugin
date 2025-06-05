<?php
add_action('rest_api_init', function () {
    register_rest_route('reactdb/v1', '/csv/read', [
        'methods' => 'GET',
        'callback' => function () {
            return CSVHandler::readCSV(WP_CONTENT_DIR . '/uploads/sample.csv');
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/csv/write', [
        'methods' => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $data = $request->get_json_params();
            CSVHandler::writeCSV(WP_CONTENT_DIR . '/uploads/sample.csv', $data);
            LogHandler::addLog(get_current_user_id(), 'CSV Update', 'Updated CSV via React DB plugin.');
            return ['status' => 'success'];
        },
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/logs', [
        'methods' => 'GET',
        'callback' => function () {
            global $wpdb;
            $table = $wpdb->prefix . 'reactdb_logs';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 10", ARRAY_A);
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    // --- Database management endpoints ---

    register_rest_route('reactdb/v1', '/tables', [
        'methods'  => 'GET',
        'callback' => function () {
            global $wpdb;
            $prefix = $wpdb->prefix . 'reactdb_';
            $tables = $wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($prefix) . '%'));
            $tables = array_map(function($t) use ($prefix) {
                return substr($t, strlen($prefix));
            }, $tables);
            return $tables;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/create', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            if (!$name) {
                return new WP_Error('invalid_name', 'Invalid table name', ['status' => 400]);
            }
            $table = $wpdb->prefix . 'reactdb_' . $name;
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, value text NOT NULL, PRIMARY KEY  (id)) $charset_collate";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            return ['status' => 'created'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/export', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
            return $rows;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/copy', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT value FROM $table WHERE id = %d", $id), ARRAY_A);
            if (!$row) {
                return new WP_Error('invalid_row', 'Row not found', ['status' => 404]);
            }
            $wpdb->insert($table, ['value' => $row['value']]);
            return ['status' => 'copied'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);
});
