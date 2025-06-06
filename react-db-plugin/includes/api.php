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

    register_rest_route('reactdb/v1', '/table/import', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;

            $files = $request->get_file_params();
            $uploaded = isset($files['file']) ? $files['file'] : null;
            if (!$uploaded || !is_uploaded_file($uploaded['tmp_name'])) {
                return new WP_Error('invalid_file', 'CSV file required', ['status' => 400]);
            }

            $name = sanitize_key($request->get_param('table'));
            if (!$name) {
                $name = pathinfo($uploaded['name'], PATHINFO_FILENAME);
                $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
                $name = sanitize_key($name);
            }
            if (!$name) {
                return new WP_Error('invalid_name', 'Invalid table name', ['status' => 400]);
            }

            $table  = $wpdb->prefix . 'reactdb_' . $name;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                return new WP_Error('table_exists', 'Table already exists', ['status' => 409]);
            }

            $rows = CSVHandler::readCSV($uploaded['tmp_name']);
            if (!$rows || count($rows) < 1) {
                return new WP_Error('invalid_csv', 'CSV is empty', ['status' => 400]);
            }

            $charset_collate = $wpdb->get_charset_collate();
            $header = array_map(function ($h) {
                $h = sanitize_key($h);
                return $h ?: 'col';
            }, array_shift($rows));

            $sample = array_slice($rows, 0, 100);
            $types = [];
            foreach ($header as $idx => $col) {
                $values = array_column($sample, $idx);
                $values = array_filter($values, function($v){ return $v !== '' && $v !== null; });
                $type = 'TEXT';
                if (!empty($values)) {
                    $is_int = true;
                    $is_float = true;
                    $is_date = true;
                    $max_len = 0;
                    foreach ($values as $v) {
                        $max_len = max($max_len, strlen($v));
                        if (!preg_match('/^-?\d+$/', $v)) { $is_int = false; }
                        if (!is_numeric($v)) { $is_float = false; }
                        if (strtotime($v) === false) { $is_date = false; }
                    }
                    if ($is_int) {
                        $type = 'BIGINT';
                    } elseif ($is_float) {
                        $type = 'DOUBLE';
                    } elseif ($is_date) {
                        $type = 'DATETIME';
                    } elseif ($max_len <= 255) {
                        $type = 'VARCHAR(255)';
                    }
                }
                $types[$idx] = $type;
            }

            $cols = ['id bigint(20) unsigned NOT NULL AUTO_INCREMENT'];
            foreach ($header as $idx => $h) {
                $cols[] = "$h {$types[$idx]}";
            }
            $cols[] = 'PRIMARY KEY  (id)';

            $sql = "CREATE TABLE $table (" . implode(',', $cols) . ") $charset_collate";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            foreach ($rows as $r) {
                $data = array();
                foreach ($header as $idx => $col) {
                    $data[$col] = isset($r[$idx]) ? $r[$idx] : '';
                }
                $wpdb->insert($table, $data);
            }

            LogHandler::addLog(get_current_user_id(), 'Import Table', $name);

            return ['status' => 'imported', 'table' => $name];
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
            $columns = $wpdb->get_col("DESC $table", 0);
            if (in_array('updated_at', $columns, true)) {
                $data['updated_at'] = current_time('mysql');
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
            $columns = $wpdb->get_col("DESC $table", 0);
            if (in_array('created_at', $columns, true) && empty($data['created_at'])) {
                $data['created_at'] = current_time('mysql');
            }
            if (in_array('updated_at', $columns, true) && empty($data['updated_at'])) {
                $data['updated_at'] = current_time('mysql');
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

    register_rest_route('reactdb/v1', '/user/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            $user = get_user_by('id', intval($request['id']));
            if (!$user) {
                return new WP_Error('invalid_user', 'User not found', ['status' => 404]);
            }
            return [
                'id'   => $user->ID,
                'name' => $user->user_login
            ];
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    // Output settings - admin access
    register_rest_route('reactdb/v1', '/output/settings', [
        'methods'  => 'GET',
        'callback' => function () {
            return OutputHandler::get_settings();
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    register_rest_route('reactdb/v1', '/output/settings', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $settings = $request->get_param('settings');
            if (!is_array($settings)) {
                return new WP_Error('invalid_settings', 'Invalid settings', ['status' => 400]);
            }
            OutputHandler::update_settings($settings);
            return OutputHandler::get_settings();
        },
        'permission_callback' => function () {
            return current_user_can('manage_options');
        }
    ]);

    // Output API - public access
    register_rest_route('reactdb/v1', '/output/(?P<task>[A-Za-z0-9_-]+)', [
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $request) {
            return OutputHandler::get_output($request['task']);
        },
        'permission_callback' => '__return_true'
    ]);
});
