<?php

/** Shared permission callback for all management endpoints. */
function reactdb_admin_permission() {
    $authenticated = reactdb_authenticated_permission();
    if (is_wp_error($authenticated)) {
        return $authenticated;
    }
    if (!current_user_can('manage_options')) {
        $auth = reactdb_current_auth_state();
        return new WP_Error('reactdb_forbidden', reactdb_auth_denial_message($auth), ['status' => 403, 'auth' => $auth]);
    }
    return true;
}

/** Only infer dates from explicit, unambiguous date formats. */
function reactdb_csv_value_is_date($value) {
    $value = trim((string) $value);
    $patterns = [
        '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/',
        '/^\d{4}\/\d{1,2}\/\d{1,2}(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?$/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return strtotime($value) !== false;
        }
    }

    return false;
}

add_action('rest_api_init', function () {
    register_rest_route('reactdb/v1', '/auth/status', [
        'methods' => 'GET',
        'callback' => function () {
            return new WP_REST_Response(reactdb_current_auth_state(), 200, ['Cache-Control' => 'private, no-store, max-age=0']);
        },
        'permission_callback' => 'reactdb_authenticated_permission'
    ]);

    register_rest_route('reactdb/v1', '/csv/read', [
        'methods' => 'GET',
        'callback' => function () {
            return CSVHandler::readCSV(WP_CONTENT_DIR . '/uploads/sample.csv');
        },
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    register_rest_route('reactdb/v1', '/csv/write', [
        'methods' => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $data = $request->get_json_params();
            CSVHandler::writeCSV(WP_CONTENT_DIR . '/uploads/sample.csv', $data);
            LogHandler::addLog(get_current_user_id(), 'CSV Update', 'Updated CSV via React DB plugin.');
            return ['status' => 'success'];
        },
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    register_rest_route('reactdb/v1', '/logs', [
        'methods' => 'GET',
        'callback' => function () {
            global $wpdb;
            $table = $wpdb->prefix . 'reactdb_logs';
            return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 10", ARRAY_A);
        },
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    register_rest_route('reactdb/v1', '/table/import', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            global $wpdb;

            $files = $request->get_file_params();
            $uploaded = isset($files['file']) ? $files['file'] : null;
            if (!$uploaded) {
                return new WP_Error('invalid_file', 'CSV file required', ['status' => 400]);
            }
            $uploadError = isset($uploaded['error']) ? (int) $uploaded['error'] : UPLOAD_ERR_OK;
            if ($uploadError !== UPLOAD_ERR_OK) {
                $uploadMessages = [
                    UPLOAD_ERR_INI_SIZE   => 'アップロードファイルがサーバーの上限サイズを超えています。',
                    UPLOAD_ERR_FORM_SIZE  => 'アップロードファイルがフォームの上限サイズを超えています。',
                    UPLOAD_ERR_PARTIAL    => 'ファイルを最後まで受信できませんでした。再試行してください。',
                    UPLOAD_ERR_NO_FILE    => 'CSVファイルを選択してください。',
                    UPLOAD_ERR_NO_TMP_DIR => 'サーバーの一時保存先が設定されていません。管理者に確認してください。',
                    UPLOAD_ERR_CANT_WRITE => 'サーバーが一時ファイルを書き込めませんでした。管理者に確認してください。',
                    UPLOAD_ERR_EXTENSION  => 'サーバーの拡張機能によりアップロードが停止されました。管理者に確認してください。',
                ];
                $message = isset($uploadMessages[$uploadError]) ? $uploadMessages[$uploadError] : 'ファイルのアップロードに失敗しました。';
                return new WP_Error('upload_failed', $message, ['status' => 400, 'upload_error' => $uploadError]);
            }
            if (empty($uploaded['tmp_name']) || !is_uploaded_file($uploaded['tmp_name'])) {
                return new WP_Error('invalid_file', 'アップロードされた一時ファイルを確認できません。再試行してください。', ['status' => 400]);
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

            $parsed = CSVHandler::parseCSV($uploaded['tmp_name'], [
                'skip_empty' => false,
            ]);
            $rows = isset($parsed['rows']) && is_array($parsed['rows']) ? $parsed['rows'] : [];
            $parseErrors = isset($parsed['errors']) && is_array($parsed['errors']) ? array_values($parsed['errors']) : [];

            $rows = array_values(array_filter($rows, function ($row) {
                if (!is_array($row)) {
                    return false;
                }
                foreach ($row as $value) {
                    if ($value !== '' && $value !== null) {
                        return true;
                    }
                }
                return false;
            }));

            if (!$rows || count($rows) < 1) {
                return new WP_Error('invalid_csv', 'CSV is empty', ['status' => 400]);
            }

            $raw_header = array_shift($rows);
            while ($raw_header !== null && is_array($raw_header)) {
                $nonEmpty = array_filter($raw_header, function ($value) {
                    return $value !== '' && $value !== null;
                });
                if (!empty($nonEmpty)) {
                    break;
                }
                $raw_header = array_shift($rows);
            }

            if ($raw_header === null) {
                return new WP_Error('invalid_csv', 'CSV header missing', ['status' => 400]);
            }

            $likelyHeader = false;
            foreach ((array) $raw_header as $value) {
                $value = is_string($value) ? trim($value) : '';
                if ($value === '') {
                    continue;
                }
                if (preg_match('/\pL/u', $value)) {
                    $likelyHeader = true;
                    break;
                }
                if (!is_numeric($value)) {
                    $likelyHeader = true;
                    break;
                }
            }

            if (!$likelyHeader) {
                array_unshift($rows, $raw_header);
                $raw_header = [];
            }

            $maxColumns = count($raw_header);
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $maxColumns = max($maxColumns, count($row));
                }
            }

            if ($maxColumns < 1) {
                return new WP_Error('invalid_csv', 'CSV data could not be parsed', ['status' => 400]);
            }

            if (count($raw_header) < $maxColumns) {
                $raw_header = array_pad($raw_header, $maxColumns, '');
            }

            $overrides_raw = $request->get_param('column_overrides');
            $overrides = [];
            if ($overrides_raw) {
                if (is_string($overrides_raw)) {
                    $decoded = json_decode($overrides_raw, true);
                } else {
                    $decoded = $overrides_raw;
                }
                if (is_array($decoded)) {
                    foreach ($decoded as $idx => $value) {
                        if (!is_numeric($idx) || !is_string($value)) {
                            continue;
                        }
                        $sanitized = sanitize_key($value);
                        if ($sanitized === '') {
                            continue;
                        }
                        $overrides[(int) $idx] = $sanitized;
                    }
                }
            }

            $headerKeys = [];
            $headerMeta = [];
            $used_keys = [];
            $invalidColumns = [];
            $preCandidates = [];

            foreach (range(0, $maxColumns - 1) as $idx) {
                $label = isset($raw_header[$idx]) ? trim((string) $raw_header[$idx]) : '';
                $autoGenerated = ($label === '');
                $candidate = '';
                $reason = '';
                $suggested = '';

                if (array_key_exists($idx, $overrides)) {
                    $candidate = $overrides[$idx];
                    if ($candidate === '') {
                        $reason = 'invalid_override';
                    }
                } elseif ($autoGenerated) {
                    $candidate = 'column_' . ($idx + 1);
                } else {
                    $candidate = sanitize_key($label);
                    if ($candidate === '' && $label !== '' && function_exists('remove_accents')) {
                        $normalized = remove_accents($label);
                        $candidate  = sanitize_key($normalized);
                    }
                    if ($candidate === '') {
                        $reason = 'non_latin';
                    }
                }

                if ($candidate !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $candidate)) {
                    $reason = array_key_exists($idx, $overrides) ? 'invalid_override' : 'invalid_start';
                }

                if ($reason !== '') {
                    $suggested = 'col_' . ($idx + 1);
                    if ($candidate !== '' && in_array($reason, ['invalid_start', 'invalid_override'], true)) {
                        $suggested = 'col_' . $candidate;
                    }
                    $invalidColumns[] = [
                        'index'     => $idx,
                        'original'  => $label,
                        'reason'    => $reason,
                        'suggested' => $suggested,
                    ];
                    continue;
                }

                $preCandidates[$idx] = [
                    'label'         => $label,
                    'candidate'     => $candidate,
                    'auto_generated'=> $autoGenerated,
                    'override_used' => array_key_exists($idx, $overrides),
                ];
            }

            if (!empty($invalidColumns)) {
                return new WP_Error('column_override_required', 'Some columns require manual overrides.', [
                    'status'  => 400,
                    'columns' => $invalidColumns,
                ]);
            }

            foreach ($preCandidates as $idx => $info) {
                $candidate = $info['candidate'];

                $maxLen = 64;
                $baseCandidate = $candidate;
                if (strlen($baseCandidate) > $maxLen) {
                    $baseCandidate = substr($baseCandidate, 0, $maxLen);
                }

                $unique = $baseCandidate;
                $suffix = 2;
                while (isset($used_keys[$unique])) {
                    $suffix_str = '_' . $suffix;
                    $available = $maxLen - strlen($suffix_str);
                    if ($available < 1) {
                        $available = $maxLen;
                    }
                    $unique = substr($baseCandidate, 0, $available) . $suffix_str;
                    $unique = substr($unique, 0, $maxLen);
                    $suffix++;
                }
                $used_keys[$unique] = true;

                $headerKeys[$idx] = $unique;
                $headerMeta[] = [
                    'key'             => $unique,
                    'label'           => $info['label'] !== '' ? $info['label'] : $unique,
                    'original'        => $info['label'],
                    'auto_generated'  => $info['auto_generated'],
                    'override_used'   => $info['override_used'],
                    'sanitized_value' => $unique,
                ];
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row = array_values($row);
                if (count($row) < $maxColumns) {
                    $row = array_pad($row, $maxColumns, '');
                } elseif (count($row) > $maxColumns) {
                    $row = array_slice($row, 0, $maxColumns);
                }
                $nonEmpty = array_filter($row, function ($value) {
                    return $value !== '' && $value !== null;
                });
                if (empty($nonEmpty)) {
                    continue;
                }
                $normalizedRows[] = $row;
            }

            if (empty($normalizedRows)) {
                return new WP_Error('invalid_csv', 'CSV rows missing', ['status' => 400]);
            }

            $charset_collate = $wpdb->get_charset_collate();

            $sample = array_slice($normalizedRows, 0, 100);
            $types = [];
            foreach ($headerKeys as $idx => $columnKey) {
                $values = array_column($sample, $idx);
                $values = array_filter($values, function($v){ return $v !== '' && $v !== null; });
                $type = 'TEXT';
                // Venue codes/names are categorical text, even if a venue label
                // happens to look numeric or date-like to PHP.
                $isVenueColumn = strtolower($columnKey) === 'venue';
                if ($isVenueColumn) {
                    $type = 'VARCHAR(255)';
                } elseif (!empty($values)) {
                    $is_int = true;
                    $is_float = true;
                    $is_date = true;
                    $max_len = 0;
                    foreach ($values as $v) {
                        $v = is_string($v) ? trim($v) : $v;
                        $max_len = max($max_len, is_string($v) ? strlen($v) : strlen((string) $v));
                        if (!preg_match('/^-?\d+$/', (string) $v)) { $is_int = false; }
                        if (!is_numeric($v)) { $is_float = false; }
                        if (!reactdb_csv_value_is_date($v)) { $is_date = false; }
                    }
                    if ($is_int) {
                        $type = 'BIGINT';
                    } elseif ($is_float) {
                        $type = 'DOUBLE';
                    } elseif ($is_date) {
                        $type = 'DATETIME';
                    } elseif ($max_len <= 255) {
                        $type = 'VARCHAR(255)';
                    } elseif ($max_len <= 65535) {
                        $type = 'TEXT';
                    } else {
                        $type = 'LONGTEXT';
                    }
                }
                $types[$idx] = $type;
            }

            $cols = ['id bigint(20) unsigned NOT NULL AUTO_INCREMENT'];
            foreach ($headerKeys as $idx => $columnKey) {
                $cols[] = "$columnKey {$types[$idx]}";
            }
            $cols[] = 'PRIMARY KEY  (id)';

            $sql = "CREATE TABLE $table (" . implode(',', $cols) . ") $charset_collate";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
                $error = $wpdb->last_error ? $wpdb->last_error : 'データベースがテーブルを作成できませんでした。';
                return new WP_Error('table_creation_failed', 'テーブルの作成に失敗しました: ' . $error, ['status' => 500]);
            }

            $preparedRows = [];
            $failedRows = 0;
            $failedSamples = [];
            $lastInsertError = '';
            foreach ($normalizedRows as $row) {
                $data = [];
                foreach ($headerKeys as $idx => $columnKey) {
                    $data[$columnKey] = isset($row[$idx]) ? $row[$idx] : '';
                }
                $inserted = $wpdb->insert($table, $data);
                if ($inserted === false) {
                    $failedRows++;
                    if ($lastInsertError === '' && $wpdb->last_error) {
                        $lastInsertError = $wpdb->last_error;
                    }
                    if (count($failedSamples) < 5) {
                        $failedSamples[] = $data;
                    }
                    continue;
                }
                $preparedRows[] = $data;
            }

            if ($failedRows > 0) {
                // Remove the incomplete table so a failed import can be retried safely.
                $wpdb->query("DROP TABLE $table");
                return new WP_Error('import_insert_failed', 'CSVデータの一部を保存できなかったため、作成したテーブルを削除しました。内容を確認して再試行してください。', [
                    'status'      => 500,
                    'failed_rows' => $failedRows,
                    'source_rows' => count($normalizedRows),
                    'reason'      => $lastInsertError !== '' ? $lastInsertError : 'データベースへの登録に失敗しました。',
                ]);
            }
            LogHandler::addLog(get_current_user_id(), 'Import Table', $name);

            $previewRows = array_slice($preparedRows, 0, 20);
            $sourceRowCount = count($normalizedRows);

            return [
                'status'   => 'imported',
                'table'    => $name,
                'preview'  => [
                    'columns'    => $headerMeta,
                    'rows'       => $previewRows,
                    'total_rows' => count($preparedRows),
                    'source_rows'=> $sourceRowCount,
                    'delimiter'  => $parsed['delimiter'],
                    'encoding'   => $parsed['encoding'],
                    'failed_rows'=> $failedRows,
                    'failure_reason' => $lastInsertError !== '' ? $lastInsertError : null,
                    'failed_samples' => $failedSamples,
                    'parse_error_count' => count($parseErrors),
                    'parse_errors'      => $parseErrors,
                ],
            ];
        },
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    register_rest_route('reactdb/v1', '/table/drop', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $name = sanitize_key($request->get_param('name'));
            if (!$name) {
                return new WP_Error('invalid_name', 'Invalid table name', ['status' => 400]);
            }

            $protected = [ 'logs' ];
            if (in_array($name, $protected, true)) {
                return new WP_Error('forbidden', 'Table cannot be deleted', ['status' => 403]);
            }

            $deleted = reactdb_drop_managed_table($name);
            if (is_wp_error($deleted)) {
                return $deleted;
            }

            LogHandler::addLog(get_current_user_id(), 'Drop Table', $name);
            $cleanup = reactdb_remove_output_settings_for_table($name);
            if (is_wp_error($cleanup)) {
                return new WP_Error('output_settings_cleanup_failed', 'テーブルは削除されましたが、関連する出力設定の削除に失敗しました。出力設定を再読み込みしてください。', ['status' => 500]);
            }
            return ['status' => 'dropped'];
        },
        'permission_callback' => 'reactdb_admin_permission'
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
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    // Output settings - admin access
    register_rest_route('reactdb/v1', '/output/settings', [
        'methods'  => 'GET',
        'callback' => function () {
            $settings = reactdb_prune_orphaned_output_settings();
            return is_wp_error($settings) ? $settings : (object) $settings;
        },
        'permission_callback' => 'reactdb_admin_permission'
    ]);

    register_rest_route('reactdb/v1', '/output/settings', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $request) {
            $settings = OutputHandler::mutate_task(
                $request->get_param('action'),
                $request->get_param('task'),
                $request->get_param('config'),
                $request->get_param('previous'),
                $request->has_param('previous')
            );
            return is_wp_error($settings) ? $settings : (object) $settings;
        },
        'permission_callback' => 'reactdb_admin_permission'
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
