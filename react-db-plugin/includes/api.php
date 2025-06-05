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
            $columns = $request->get_param('columns');
            if (!$name) {
                return new WP_Error('invalid_name', 'Invalid table name', ['status' => 400]);
            }

            $table = $wpdb->prefix . 'reactdb_' . $name;
            $charset_collate = $wpdb->get_charset_collate();

            $cols = [ 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT' ];
            if (is_array($columns)) {
                $allowed = [ 'INT', 'VARCHAR(255)', 'TEXT', 'DATETIME' ];
                foreach ($columns as $col) {
                    $cname = isset($col['name']) ? sanitize_key($col['name']) : '';
                    $ctype = isset($col['type']) ? strtoupper($col['type']) : '';
                    $default = isset($col['default']) ? $col['default'] : null;
                    if (!$cname || !in_array($ctype, $allowed, true)) {
                        continue;
                    }
                    $def = '';
                    if ($default !== null && $default !== '') {
                        $def = " DEFAULT '" . esc_sql($default) . "'";
                    }
                    $cols[] = "$cname $ctype$def";
                }
            }
            $cols[] = 'PRIMARY KEY  (id)';

            $sql = "CREATE TABLE $table (" . implode(',', $cols) . ") $charset_collate";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            LogHandler::addLog(get_current_user_id(), 'Create Table', $name);

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
            $name     = sanitize_key($request->get_param('name'));      // source table
            $new_name = sanitize_key($request->get_param('new_name'));  // destination table
            if (!$name || !$new_name) {
                return new WP_Error('invalid_name', 'Invalid table name', ['status' => 400]);
            }

            $src = $wpdb->prefix . 'reactdb_' . $name;
            $dst = $wpdb->prefix . 'reactdb_' . $new_name;

            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $src))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $dst))) {
                return new WP_Error('table_exists', 'Table already exists', ['status' => 409]);
            }

            $wpdb->query("CREATE TABLE $dst LIKE $src");
            LogHandler::addLog(get_current_user_id(), 'Copy Table', "$name to $new_name");
            return ['status' => 'copied'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/info', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $cols = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
            return $cols;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/row', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if (!$row) {
                return new WP_Error('invalid_row', 'Row not found', ['status' => 404]);
            }
            return $row;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/update', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $data = $request->get_param('data');
            if (!is_array($data)) {
                return new WP_Error('invalid_data', 'Invalid data', ['status' => 400]);
            }
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $wpdb->update($table, $data, ['id' => $id]);
            LogHandler::addLog(get_current_user_id(), 'Update Row', $name);
            return ['status' => 'updated'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/addrow', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $data = $request->get_param('data');
            if (!is_array($data)) {
                return new WP_Error('invalid_data', 'Invalid data', ['status' => 400]);
            }
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $wpdb->insert($table, $data);
            LogHandler::addLog(get_current_user_id(), 'Insert Row', $name);
            return ['status' => 'inserted'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/delete', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $wpdb->delete($table, ['id' => $id]);
            LogHandler::addLog(get_current_user_id(), 'Delete Row', $name);
            return ['status' => 'deleted'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/info', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $cols = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
            return $cols;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/row', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
            if (!$row) {
                return new WP_Error('invalid_row', 'Row not found', ['status' => 404]);
            }
            return $row;
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/update', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $id   = intval($request->get_param('id'));
            $data = $request->get_param('data');
            if (!is_array($data)) {
                return new WP_Error('invalid_data', 'Invalid data', ['status' => 400]);
            }
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $wpdb->update($table, $data, ['id' => $id]);
            LogHandler::addLog(get_current_user_id(), 'Update Row', $name);
            return ['status' => 'updated'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/table/addrow', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;
            $name = sanitize_key($request->get_param('name'));
            $data = $request->get_param('data');
            if (!is_array($data)) {
                return new WP_Error('invalid_data', 'Invalid data', ['status' => 400]);
            }
            $table = $wpdb->prefix . 'reactdb_' . $name;
            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('invalid_table', 'Table not found', ['status' => 404]);
            }
            $wpdb->insert($table, $data);
            LogHandler::addLog(get_current_user_id(), 'Insert Row', $name);
            return ['status' => 'inserted'];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);
});
