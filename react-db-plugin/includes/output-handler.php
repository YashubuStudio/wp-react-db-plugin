<?php
class OutputHandler {
    private const FILTER_MULTI_SEPARATOR = '|~|';
    private const SORT_DIRECTIONS = ['asc', 'desc'];
    public static function get_settings() {
        $settings = get_option('reactdb_output_settings', []);
        return is_array($settings) ? $settings : [];
    }

    public static function get_task($task) {
        $settings = self::get_settings();
        return isset($settings[$task]) ? $settings[$task] : null;
    }

    /** Read the database directly so stale option caches cannot overwrite code. */
    public static function read_settings_snapshot() {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            'reactdb_output_settings'
        ));
        if ($wpdb->last_error !== '') {
            return new WP_Error('output_settings_read_failed', '出力設定をデータベースから取得できませんでした。', ['status' => 500]);
        }
        $settings = $raw === null ? [] : maybe_unserialize($raw);
        if (!is_array($settings)) {
            return new WP_Error('invalid_stored_settings', '保存済みの出力設定が不正です。データを保護するため上書きを中止しました。', ['status' => 500]);
        }
        return ['raw' => $raw, 'settings' => $settings];
    }

    private static function settings_conflict() {
        return new WP_Error('output_settings_conflict', '別の画面で設定が更新されています。入力コードを控えてから再読み込みし、最新の設定で再保存してください。', ['status' => 409]);
    }

    /** Compare-and-swap protects both task edits and automatic cleanup. */
    public static function store_settings_snapshot($settings, $previous_raw) {
        global $wpdb;
        $serialized = maybe_serialize($settings);
        if ($previous_raw === null) {
            $updated = add_option('reactdb_output_settings', $settings, '', false);
            if (!$updated) {
                return $wpdb->last_error !== ''
                    ? new WP_Error('output_settings_write_failed', '出力設定を保存できませんでした。データベースの書込権限や容量を確認してください。', ['status' => 500])
                    : self::settings_conflict();
            }
        } elseif ($serialized !== $previous_raw) {
            // BINARY is essential: code differing only in case must not compare equal.
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s",
                $serialized,
                'reactdb_output_settings',
                $previous_raw
            ));
            if ($updated === false) {
                return new WP_Error('output_settings_write_failed', '出力設定を保存できませんでした。データベースの書込権限や容量を確認してください。', ['status' => 500]);
            }
            if ($updated === 0) {
                return self::settings_conflict();
            }
        }

        wp_cache_delete('reactdb_output_settings', 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        $stored = self::read_settings_snapshot();
        if (is_wp_error($stored)) {
            return $stored;
        }
        if ($stored['raw'] !== $serialized) {
            return self::settings_conflict();
        }
        return $stored['settings'];
    }

    /** Only the requested task is changed; other tasks are never resubmitted. */
    public static function mutate_task($action, $task, $config, $previous, $has_previous) {
        global $wpdb;
        if (!in_array($action, ['create', 'save', 'delete'], true)) {
            return new WP_Error('output_editor_outdated', '保存画面が古いか、操作の指定が不正です。入力コードを控えてページを再読み込みしてください。', ['status' => 400]);
        }
        if (!is_string($task) || !preg_match('/^[A-Za-z0-9_-]+$/', $task)) {
            return new WP_Error('invalid_task', 'タスク名は半角英数字、ハイフン、アンダースコアで指定してください。', ['status' => 400]);
        }
        $snapshot = self::read_settings_snapshot();
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }
        $settings = $snapshot['settings'];
        $exists = array_key_exists($task, $settings);
        if ($action === 'create' && $exists) {
            return new WP_Error('output_task_exists', '同じタスク名が存在します。既存のリンクから編集するか、別の名前を指定してください。', ['status' => 409]);
        }
        if ($action !== 'create' && (!$exists || !$has_previous || $settings[$task] !== $previous)) {
            return self::settings_conflict();
        }
        if ($action === 'delete') {
            unset($settings[$task]);
        } else {
            if (is_array($config) && $exists && is_array($settings[$task])) {
                $config = array_replace($settings[$task], $config);
            }
            $normalized = self::normalize_settings([$task => $config], $settings);
            if (is_wp_error($normalized)) {
                return $normalized;
            }
            $table = $wpdb->prefix . 'reactdb_' . $normalized[$task]['table'];
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
            if ($wpdb->last_error !== '') {
                return new WP_Error('output_table_read_failed', '出力元テーブルを確認できませんでした。', ['status' => 500]);
            }
            if ($found !== $table) {
                return new WP_Error('output_table_missing', '出力元のテーブルが存在しません。テーブルを選び直してください。', ['status' => 400]);
            }
            $settings[$task] = $normalized[$task];
        }
        return self::store_settings_snapshot($settings, $snapshot['raw']);
    }

    private static function normalize_settings($settings, $previous = []) {
        if (!is_array($settings)) {
            return new WP_Error('invalid_settings', '出力設定の形式が不正です。', ['status' => 400]);
        }
        foreach ($settings as $task => $conf) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $task) || !is_array($conf)) {
                return new WP_Error('invalid_task', 'タスク名または設定の形式が不正です。', ['status' => 400]);
            }
            if (empty($conf['table']) || !is_string($conf['table']) || sanitize_key($conf['table']) !== $conf['table']) {
                return new WP_Error('invalid_table', '出力元のテーブル名が不正です。', ['status' => 400]);
            }
            if (!isset($conf['format']) || !in_array($conf['format'], ['html', 'json'], true)) {
                return new WP_Error('invalid_format', '出力形式はHTMLまたはJSONを指定してください。', ['status' => 400]);
            }
            foreach (['html', 'css', 'filterCss'] as $field) {
                if (isset($conf[$field]) && !is_string($conf[$field])) {
                    return new WP_Error('invalid_code', $field . 'は文字列で指定してください。', ['status' => 400]);
                }
                // REST JSON is already unslashed. Preserve quotes, backslashes,
                // template placeholders, newlines and CSS content verbatim.
                $settings[$task][$field] = isset($conf[$field]) ? $conf[$field] : '';
            }
            $html = $settings[$task]['html'];
            $old_html = isset($previous[$task]['html']) ? $previous[$task]['html'] : '';
            if ($html !== $old_html && !current_user_can('unfiltered_html') && wp_kses_post($html) !== $html) {
                return new WP_Error('reactdb_html_permission', 'このHTMLを変更せず保存するにはunfiltered_html権限が必要です。入力したコードは保持されています。マルチサイトでは特権管理者で操作してください。', ['status' => 403]);
            }
            foreach (['css', 'filterCss'] as $field) {
                if (preg_match('/<\/?style\b/i', $settings[$task][$field])) {
                    return new WP_Error('invalid_css', $field . 'には<style>タグを含めず、CSS本体だけを入力してください。', ['status' => 400]);
                }
            }
            if (isset($conf['dateField'])) {
                $settings[$task]['dateField'] = sanitize_text_field($conf['dateField']);
            } elseif (!isset($settings[$task]['dateField'])) {
                $settings[$task]['dateField'] = '';
            }
            if (isset($conf['categoryField'])) {
                $settings[$task]['categoryField'] = sanitize_text_field($conf['categoryField']);
            } elseif (!isset($settings[$task]['categoryField'])) {
                $settings[$task]['categoryField'] = '';
            }
            if (isset($conf['filters']) && is_array($conf['filters'])) {
                $filters = [];
                foreach ($conf['filters'] as $filter) {
                    if (!is_array($filter)) {
                        continue;
                    }
                    $column = isset($filter['column']) ? sanitize_text_field($filter['column']) : '';
                    $id = isset($filter['id']) ? sanitize_key($filter['id']) : '';
                    if ($id === '' && $column !== '') {
                        $id = sanitize_key($column);
                    }
                    if ($id === '') {
                        $id = 'filter_' . (count($filters) + 1);
                    }
                    $label = isset($filter['label']) ? sanitize_text_field($filter['label']) : '';
                    if ($label === '' && $column !== '') {
                        $label = $column;
                    }
                    $type = isset($filter['type']) && in_array($filter['type'], ['text', 'date', 'list'], true)
                        ? $filter['type']
                        : 'text';
                    $dateFormat = isset($filter['dateFormat']) ? sanitize_text_field($filter['dateFormat']) : 'Y-m-d';
                    $delimiter = isset($filter['delimiter']) && $filter['delimiter'] !== ''
                        ? sanitize_text_field($filter['delimiter'])
                        : ',';
                    $sort = isset($filter['sort']) && in_array($filter['sort'], ['asc', 'desc', 'none'], true)
                        ? $filter['sort']
                        : 'asc';
                    $labelTemplate = isset($filter['labelTemplate']) ? sanitize_text_field($filter['labelTemplate']) : '';
                    $hidden = !empty($filter['hidden']);
                    $filters[] = [
                        'id' => $id,
                        'label' => $label,
                        'column' => $column,
                        'type' => $type,
                        'dateFormat' => $dateFormat,
                        'delimiter' => $delimiter,
                        'sort' => $sort,
                        'labelTemplate' => $labelTemplate,
                        'hidden' => $hidden,
                    ];
                }
                $settings[$task]['filters'] = $filters;
            } elseif (!isset($settings[$task]['filters'])) {
                $settings[$task]['filters'] = [];
            }
            if (isset($conf['search']) && is_array($conf['search'])) {
                $enabled = !empty($conf['search']['enabled']);
                $columns = [];
                if (!empty($conf['search']['columns']) && is_array($conf['search']['columns'])) {
                    foreach ($conf['search']['columns'] as $column) {
                        if (!is_string($column)) {
                            continue;
                        }
                        $column = sanitize_text_field($column);
                        if ($column === '') {
                            continue;
                        }
                        $columns[$column] = true;
                    }
                }
                $columns = array_keys($columns);
                $settings[$task]['search'] = [
                    'enabled' => $enabled && !empty($columns),
                    'columns' => $columns,
                ];
            } elseif (!isset($settings[$task]['search'])) {
                $settings[$task]['search'] = [
                    'enabled' => false,
                    'columns' => [],
                ];
            }
            $settings[$task]['parameterControl'] = self::sanitize_parameter_control_config(
                isset($conf['parameterControl']) ? $conf['parameterControl'] : [],
                $settings[$task]['filters']
            );
            $settings[$task]['defaultSort'] = self::sanitize_default_sort_config(
                isset($conf['defaultSort']) ? $conf['defaultSort'] : []
            );
        }
        return $settings;
    }

    public static function get_rows($table) {
        global $wpdb;
        $table = $wpdb->prefix . 'reactdb_' . sanitize_key($table);
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return [];
        }
        return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    }

    private static function normalize_parameter_source($params) {
        if (!is_array($params)) {
            return [];
        }
        $normalized = [];
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    private static function sanitize_parameter_control_config($config, $filters) {
        $defaults = [
            'allowShortcode' => false,
            'allowUrl' => false,
            'filters' => [],
            'sortColumns' => [],
        ];
        if (!is_array($config)) {
            $config = [];
        }

        $filterKeys = [];
        if (is_array($filters)) {
            foreach ($filters as $filter) {
                if (!is_array($filter)) {
                    continue;
                }
                if (isset($filter['id'])) {
                    $id = sanitize_key($filter['id']);
                } elseif (isset($filter['key'])) {
                    $id = sanitize_key($filter['key']);
                } else {
                    $id = '';
                }
                if ($id === '') {
                    continue;
                }
                $filterKeys[$id] = true;
            }
        }

        $allowedFilters = [];
        if (isset($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $filterKey) {
                $filterKey = sanitize_key($filterKey);
                if ($filterKey === '') {
                    continue;
                }
                if (!empty($filterKeys) && !isset($filterKeys[$filterKey])) {
                    continue;
                }
                $allowedFilters[$filterKey] = true;
            }
        }

        $allowedSortColumns = [];
        if (isset($config['sortColumns']) && is_array($config['sortColumns'])) {
            foreach ($config['sortColumns'] as $column) {
                if (!is_string($column)) {
                    continue;
                }
                $column = sanitize_text_field($column);
                if ($column === '') {
                    continue;
                }
                $allowedSortColumns[$column] = true;
            }
        }

        return [
            'allowShortcode' => !empty($config['allowShortcode']),
            'allowUrl' => !empty($config['allowUrl']),
            'filters' => array_keys($allowedFilters),
            'sortColumns' => array_keys($allowedSortColumns),
        ] + $defaults;
    }

    private static function sanitize_default_sort_config($config) {
        if (!is_array($config)) {
            $config = [];
        }
        $column = isset($config['column']) ? sanitize_text_field($config['column']) : '';
        $direction = isset($config['direction']) ? strtolower(sanitize_text_field($config['direction'])) : 'asc';
        if (!in_array($direction, self::SORT_DIRECTIONS, true)) {
            $direction = 'asc';
        }
        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    private static function get_available_columns($rows) {
        $columns = [];
        if (!is_array($rows)) {
            return $columns;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $column => $_) {
                if (!is_string($column)) {
                    continue;
                }
                $columns[$column] = true;
            }
        }
        return array_keys($columns);
    }

    private static function extract_filter_params($source, $allowedKeys) {
        $result = [];
        if (!is_array($source) || empty($allowedKeys)) {
            return $result;
        }
        $allowed = [];
        foreach ($allowedKeys as $key) {
            $allowed[$key] = true;
        }
        foreach ($allowed as $key => $_) {
            $paramKeys = [
                'filter_' . $key,
                'filter-' . $key,
                $key,
            ];
            foreach ($paramKeys as $paramKey) {
                if (!array_key_exists($paramKey, $source)) {
                    continue;
                }
                $value = $source[$paramKey];
                if (is_array($value)) {
                    continue;
                }
                $value = sanitize_text_field(wp_unslash($value));
                if ($value === '') {
                    continue;
                }
                $result[$key] = $value;
                break;
            }
        }
        return $result;
    }

    private static function extract_sort_params($source, $allowedColumns) {
        if (!is_array($source) || empty($allowedColumns)) {
            return null;
        }
        $allowed = [];
        foreach ($allowedColumns as $column) {
            $allowed[$column] = true;
        }
        $column = '';
        foreach (['sort', 'sort_column', 'sort-column'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if (is_array($value)) {
                continue;
            }
            $value = sanitize_text_field(wp_unslash($value));
            if ($value === '') {
                continue;
            }
            if (!isset($allowed[$value])) {
                continue;
            }
            $column = $value;
            break;
        }
        if ($column === '') {
            return null;
        }
        $direction = 'asc';
        foreach (['order', 'direction', 'sort_order', 'sort-direction'] as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }
            $value = $source[$key];
            if (is_array($value)) {
                continue;
            }
            $value = strtolower(sanitize_text_field(wp_unslash($value)));
            if (in_array($value, self::SORT_DIRECTIONS, true)) {
                $direction = $value;
                break;
            }
        }
        return [
            'column' => $column,
            'direction' => $direction,
        ];
    }

    private static function apply_sorting($rows, $column, $direction) {
        if (!is_array($rows) || empty($rows) || $column === '') {
            return $rows;
        }
        $hasColumn = false;
        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($column, $row)) {
                $hasColumn = true;
                break;
            }
        }
        if (!$hasColumn) {
            return $rows;
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        usort($rows, function ($a, $b) use ($column, $direction) {
            $valueA = is_array($a) && array_key_exists($column, $a) ? $a[$column] : null;
            $valueB = is_array($b) && array_key_exists($column, $b) ? $b[$column] : null;
            $numericA = is_numeric($valueA);
            $numericB = is_numeric($valueB);
            if ($numericA && $numericB) {
                $valueA = (float) $valueA;
                $valueB = (float) $valueB;
            } else {
                $valueA = is_scalar($valueA) ? (string) $valueA : '';
                $valueB = is_scalar($valueB) ? (string) $valueB : '';
                $cmp = strnatcasecmp($valueA, $valueB);
                if ($cmp !== 0) {
                    return $direction === 'desc' ? -$cmp : $cmp;
                }
                return 0;
            }
            if ($valueA === $valueB) {
                return 0;
            }
            if ($direction === 'desc') {
                return ($valueA < $valueB) ? 1 : -1;
            }
            return ($valueA < $valueB) ? -1 : 1;
        });
        return array_values($rows);
    }

    private static function get_filter_values($rawValue, $type, $dateFormat, $delimiter) {
        $values = [];
        if (is_array($rawValue)) {
            return $values;
        }
        $stringValue = trim((string) $rawValue);
        if ($stringValue === '') {
            return $values;
        }
        if ($type === 'list') {
            $separator = $delimiter !== '' ? $delimiter : ',';
            $parts = explode($separator, $stringValue);
            $unique = [];
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $unique[$part] = true;
            }
            return array_keys($unique);
        }
        if ($type === 'date') {
            $timestamp = strtotime($stringValue);
            if ($timestamp !== false && $timestamp !== -1) {
                $format = $dateFormat !== '' ? $dateFormat : 'Y-m-d';
                $values[] = date_i18n($format, $timestamp);
            }
            return $values;
        }
        $values[] = $stringValue;
        return $values;
    }

    private static function sort_filter_values($values, $direction) {
        if (!is_array($values)) {
            return [];
        }
        $normalized = [];
        foreach ($values as $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }
        if ($direction === 'none') {
            return $normalized;
        }
        $sorted = $normalized;
        natcasesort($sorted);
        $sorted = array_values($sorted);
        if ($direction === 'desc') {
            $sorted = array_reverse($sorted);
        }
        return $sorted;
    }

    private static function build_filters($rawFilters, $rows) {
        if (!is_array($rawFilters) || empty($rawFilters)) {
            return [];
        }
        $prepared = [];
        $index = 0;
        foreach ($rawFilters as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $column = isset($filter['column']) ? (string) $filter['column'] : '';
            $key = isset($filter['id']) ? sanitize_key($filter['id']) : '';
            if ($key === '' && $column !== '') {
                $key = sanitize_key($column);
            }
            if ($key === '') {
                $key = 'filter_' . ($index + 1);
            }
            $label = isset($filter['label']) ? (string) $filter['label'] : '';
            if ($label === '') {
                $label = $column !== '' ? $column : sprintf('フィルター%d', $index + 1);
            }
            $type = isset($filter['type']) ? (string) $filter['type'] : 'text';
            if (!in_array($type, ['text', 'date', 'list'], true)) {
                $type = 'text';
            }
            $dateFormat = isset($filter['dateFormat']) && $filter['dateFormat'] !== '' ? (string) $filter['dateFormat'] : 'Y-m-d';
            $delimiter = isset($filter['delimiter']) && $filter['delimiter'] !== '' ? (string) $filter['delimiter'] : ',';
            $sort = isset($filter['sort']) ? (string) $filter['sort'] : 'asc';
            if (!in_array($sort, ['asc', 'desc', 'none'], true)) {
                $sort = 'asc';
            }
            $labelTemplate = isset($filter['labelTemplate']) ? (string) $filter['labelTemplate'] : '';
            $hidden = !empty($filter['hidden']);
            $valueSet = [];
            if ($column !== '') {
                foreach ($rows as $row) {
                    if (!is_array($row) || !array_key_exists($column, $row)) {
                        continue;
                    }
                    $rawValue = $row[$column];
                    $values = self::get_filter_values($rawValue, $type, $dateFormat, $delimiter);
                    foreach ($values as $value) {
                        $value = (string) $value;
                        if ($value === '') {
                            continue;
                        }
                        $valueSet[$value] = true;
                    }
                }
            }
            $options = [];
            $sortedValues = self::sort_filter_values(array_keys($valueSet), $sort);
            foreach ($sortedValues as $value) {
                $display = $labelTemplate !== '' ? str_replace('{{value}}', $value, $labelTemplate) : $value;
                $options[] = [
                    'value' => $value,
                    'label' => $display,
                ];
            }
            $prepared[] = [
                'key' => $key,
                'label' => $label,
                'column' => $column,
                'type' => $type,
                'dateFormat' => $dateFormat,
                'delimiter' => $delimiter,
                'sort' => $sort,
                'labelTemplate' => $labelTemplate,
                'hidden' => $hidden,
                'options' => $options,
            ];
            $index++;
        }
        return $prepared;
    }

    private static function build_item_attributes($row, $filters) {
        if (!is_array($filters) || empty($filters) || !is_array($row)) {
            return '';
        }
        $parts = [];
        foreach ($filters as $filter) {
            if (!isset($filter['key'])) {
                continue;
            }
            $key = $filter['key'];
            $column = isset($filter['column']) ? $filter['column'] : '';
            $type = isset($filter['type']) ? $filter['type'] : 'text';
            $dateFormat = isset($filter['dateFormat']) ? $filter['dateFormat'] : 'Y-m-d';
            $delimiter = isset($filter['delimiter']) ? $filter['delimiter'] : ',';
            $values = [];
            if ($column !== '' && array_key_exists($column, $row)) {
                $values = self::get_filter_values($row[$column], $type, $dateFormat, $delimiter);
            }
            $unique = [];
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $unique[$value] = true;
            }
            $attrValue = '';
            if (!empty($unique)) {
                $attrValue = implode(self::FILTER_MULTI_SEPARATOR, array_keys($unique));
            }
            $parts[] = ' data-filter-' . esc_attr($key) . '="' . esc_attr($attrValue) . '"';
        }
        return implode('', $parts);
    }

    private static function build_search_attribute($row, $columns) {
        if (!is_array($row) || !is_array($columns) || empty($columns)) {
            return '';
        }
        $pieces = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                continue;
            }
            if (!array_key_exists($column, $row)) {
                continue;
            }
            $value = $row[$column];
            if (is_array($value)) {
                $value = join(' ', array_map('strval', $value));
            }
            $text = wp_strip_all_tags((string) $value);
            if ($text === '') {
                continue;
            }
            $pieces[] = preg_replace('/\s+/u', ' ', $text);
        }
        if (empty($pieces)) {
            return '';
        }
        $joined = join(' ', $pieces);
        $content = function_exists('mb_strtolower') ? mb_strtolower($joined, 'UTF-8') : strtolower($joined);
        return ' data-search-index="' . esc_attr($content) . '"';
    }

    private static function get_search_config($config) {
        if (!is_array($config) || empty($config['search']) || !is_array($config['search'])) {
            return [
                'enabled' => false,
                'columns' => [],
            ];
        }
        $search = $config['search'];
        $enabled = !empty($search['enabled']);
        $columns = [];
        if (!empty($search['columns']) && is_array($search['columns'])) {
            foreach ($search['columns'] as $column) {
                if (!is_string($column)) {
                    continue;
                }
                $column = sanitize_text_field($column);
                if ($column === '') {
                    continue;
                }
                $columns[$column] = true;
            }
        }
        $columns = array_keys($columns);
        if (empty($columns)) {
            $enabled = false;
        }
        return [
            'enabled' => $enabled,
            'columns' => $columns,
        ];
    }

    public static function render_template($template, $row) {
        // Substitute once so data containing {{another_column}} stays literal.
        return preg_replace_callback('/{{([^{}]+)}}/', function ($match) use ($row) {
            return array_key_exists($match[1], $row) ? esc_html($row[$match[1]] ?? '') : $match[0];
        }, $template);
    }

    public static function render_html($task, $shortcodeAtts = []) {
        $config = self::get_task($task);
        if (!$config) {
            return '<div>No settings</div>';
        }
        $rows = self::get_rows($config['table']);
        if (!$rows) {
            return '<div>No data</div>';
        }
        $css = !empty($config['css']) ? trim($config['css']) : '';
        $search = self::get_search_config($config);
        $hasSearch = $search['enabled'] && !empty($search['columns']);
        $rawFilters = [];
        if (isset($config['filters']) && is_array($config['filters'])) {
            $rawFilters = $config['filters'];
        }
        if (empty($rawFilters)) {
            $dateField = isset($config['dateField']) ? $config['dateField'] : '';
            if ($dateField !== '') {
                $rawFilters[] = [
                    'id' => 'date',
                    'label' => '日付',
                    'column' => $dateField,
                    'type' => 'date',
                    'dateFormat' => 'Y-m-d',
                    'delimiter' => ',',
                    'sort' => 'asc',
                    'labelTemplate' => '',
                ];
            }
            $categoryField = isset($config['categoryField']) ? $config['categoryField'] : '';
            if ($categoryField !== '') {
                $rawFilters[] = [
                    'id' => 'category',
                    'label' => 'カテゴリ',
                    'column' => $categoryField,
                    'type' => 'text',
                    'dateFormat' => 'Y-m-d',
                    'delimiter' => ',',
                    'sort' => 'asc',
                    'labelTemplate' => '',
                ];
            }
        }
        $filters = self::build_filters($rawFilters, $rows);
        $parameterControl = self::sanitize_parameter_control_config(
            isset($config['parameterControl']) ? $config['parameterControl'] : [],
            $filters
        );
        $defaultSort = self::sanitize_default_sort_config(
            isset($config['defaultSort']) ? $config['defaultSort'] : []
        );
        $availableColumns = self::get_available_columns($rows);
        if (!in_array($defaultSort['column'], $availableColumns, true)) {
            $defaultSort['column'] = '';
        }
        $hasFilters = !empty($filters);
        $hasVisibleFilters = false;
        if ($hasFilters) {
            foreach ($filters as $filter) {
                if (empty($filter['hidden'])) {
                    $hasVisibleFilters = true;
                    break;
                }
            }
        }
        $interactive = $hasFilters || $hasSearch;
        $filterKeys = [];
        foreach ($filters as $filter) {
            if (isset($filter['key']) && $filter['key'] !== '') {
                $filterKeys[] = $filter['key'];
            }
        }
        $allowedFilterKeys = array_values(array_intersect($parameterControl['filters'], $filterKeys));
        $allowedSortColumns = array_values(array_intersect($parameterControl['sortColumns'], $availableColumns));

        $shortcodeParams = self::normalize_parameter_source($shortcodeAtts);
        unset($shortcodeParams['task']);
        $initialFilters = [];
        $activeSort = $defaultSort;
        if ($parameterControl['allowShortcode']) {
            $shortcodeFilters = self::extract_filter_params($shortcodeParams, $allowedFilterKeys);
            foreach ($shortcodeFilters as $key => $value) {
                $initialFilters[$key] = $value;
            }
            $shortcodeSort = self::extract_sort_params($shortcodeParams, $allowedSortColumns);
            if ($shortcodeSort) {
                $activeSort = $shortcodeSort;
            }
        }
        if ($parameterControl['allowUrl']) {
            $urlParams = [];
            foreach ($_GET as $key => $value) {
                if (is_array($value)) {
                    continue;
                }
                $urlParams[$key] = wp_unslash($value);
            }
            $urlFilters = self::extract_filter_params($urlParams, $allowedFilterKeys);
            foreach ($urlFilters as $key => $value) {
                $initialFilters[$key] = $value;
            }
            $urlSort = self::extract_sort_params($urlParams, $allowedSortColumns);
            if ($urlSort) {
                $activeSort = $urlSort;
            }
        }
        if ($activeSort['column'] !== '' && !in_array($activeSort['column'], $availableColumns, true)) {
            $activeSort['column'] = '';
        }
        if ($activeSort['column'] !== '') {
            $rows = self::apply_sorting($rows, $activeSort['column'], $activeSort['direction']);
        }

        if (!empty($initialFilters) && $hasFilters) {
            $validatedFilters = [];
            foreach ($filters as $filter) {
                if (!isset($filter['key']) || $filter['key'] === '') {
                    continue;
                }
                $key = $filter['key'];
                if (!isset($initialFilters[$key])) {
                    continue;
                }
                $value = $initialFilters[$key];
                if ($value === '') {
                    continue;
                }
                $options = isset($filter['options']) && is_array($filter['options']) ? $filter['options'] : [];
                if (empty($options)) {
                    $validatedFilters[$key] = $value;
                    continue;
                }
                foreach ($options as $option) {
                    if (!is_array($option) || !isset($option['value'])) {
                        continue;
                    }
                    if ((string) $option['value'] === (string) $value) {
                        $validatedFilters[$key] = $value;
                        break;
                    }
                }
            }
            $initialFilters = $validatedFilters;
        }

        $frontendConfig = [];
        if (!empty($initialFilters)) {
            $frontendConfig['initialFilters'] = $initialFilters;
        }
        if (!empty($activeSort['column'])) {
            $frontendConfig['activeSort'] = $activeSort;
        }
        if ($parameterControl['allowShortcode'] || $parameterControl['allowUrl']) {
            $frontendConfig['parameterControl'] = [
                'allowShortcode' => $parameterControl['allowShortcode'],
                'allowUrl' => $parameterControl['allowUrl'],
                'filters' => $allowedFilterKeys,
                'sortColumns' => $allowedSortColumns,
            ];
        }
        $containerId = 'reactdb-output-' . uniqid();
        $asset_dir = plugin_dir_path(__FILE__) . '../assets/';
        $plugin_file = dirname(__DIR__) . '/react-db-plugin.php';
        $css_path = $asset_dir . 'output-tabs.css';
        $js_path = $asset_dir . 'output-tabs.js';
        $css_version = file_exists($css_path) ? filemtime($css_path) : false;
        $js_version = file_exists($js_path) ? filemtime($js_path) : false;

        ob_start();
        if ($interactive) {
            $css_url = plugins_url('assets/output-tabs.css', $plugin_file);
            $js_url = plugins_url('assets/output-tabs.js', $plugin_file);
            wp_enqueue_style('reactdb-output-tabs', $css_url, [], $css_version);
            wp_enqueue_script('reactdb-output-tabs', $js_url, [], $js_version, true);
        }
        $filterCss = !empty($config['filterCss']) ? trim($config['filterCss']) : '';

        if ($css !== '') {
            echo '<style>' . $css . '</style>';
        }
        if ($interactive && $filterCss !== '') {
            echo '<style>' . $filterCss . '</style>';
        }

        if ($interactive) {
            $configAttr = '';
            if (!empty($frontendConfig)) {
                $configAttr = ' data-reactdb-config="' . esc_attr(wp_json_encode($frontendConfig)) . '"';
            }
            echo '<div class="reactdb-tabbed-output" data-reactdb-tabbed-output="1" id="' . esc_attr($containerId) . '" data-reactdb-task="' . esc_attr($task) . '"' . $configAttr . '>';
            if ($hasSearch || $hasFilters) {
                $controlPanelClasses = ['reactdb-output-controlPanel'];
                if ($hasFilters && !$hasSearch && !$hasVisibleFilters) {
                    $controlPanelClasses[] = 'reactdb-output-controlPanel--hidden';
                }
                echo '<div class="' . esc_attr(implode(' ', $controlPanelClasses)) . '">';
                if ($hasSearch) {
                    echo '<div class="reactdb-output-searchBlock"><label class="reactdb-output-searchLabel" for="' . esc_attr($containerId) . '-search">';
                    echo '<span class="reactdb-output-searchTitle">キーワード検索</span>';
                    echo '<input type="search" class="reactdb-output-searchInput" id="' . esc_attr($containerId) . '-search" placeholder="キーワードで絞り込み" />';
                    echo '</label></div>';
                }
                foreach ($filters as $filter) {
                    if (!isset($filter['key']) || $filter['key'] === '') {
                        continue;
                    }
                    $options = isset($filter['options']) && is_array($filter['options']) ? $filter['options'] : [];
                    $groupClasses = [
                        'reactdb-output-filterGroup',
                        'reactdb-output-filterGroup-' . $filter['key'],
                    ];
                    if (!empty($filter['hidden'])) {
                        $groupClasses[] = 'is-hidden';
                    }
                    echo '<div class="' . esc_attr(implode(' ', $groupClasses)) . '" data-filter="' . esc_attr($filter['key']) . '">';
                    echo '<div class="reactdb-output-filterTitle">' . esc_html($filter['label']) . '</div>';
                    echo '<div class="reactdb-output-filterList">';
                    echo '<button type="button" class="reactdb-output-filterButton is-active" data-value="" data-default="1">すべて</button>';
                    foreach ($options as $option) {
                        if (!is_array($option) || !isset($option['value'])) {
                            continue;
                        }
                        $label = isset($option['label']) ? $option['label'] : $option['value'];
                        echo '<button type="button" class="reactdb-output-filterButton" data-value="' . esc_attr($option['value']) . '">' . esc_html($label) . '</button>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '<div class="reactdb-tabbed-content">';
            echo '<div class="reactdb-output-items">';
        }

        if (!empty($config['html'])) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $html = self::render_template($config['html'], $row);
                if ($interactive) {
                    $attributes = '';
                    if ($hasFilters) {
                        $attributes .= self::build_item_attributes($row, $filters);
                    }
                    if ($hasSearch) {
                        $attributes .= self::build_search_attribute($row, $search['columns']);
                    }
                    echo '<div class="reactdb-item"' . $attributes . '>' . $html . '</div>';
                } else {
                    echo $html;
                }
            }
        } else {
            if ($interactive) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $content = '<div class="reactdb-default-row">' . esc_html(join(' | ', $row)) . '</div>';
                    $attributes = '';
                    if ($hasFilters) {
                        $attributes .= self::build_item_attributes($row, $filters);
                    }
                    if ($hasSearch) {
                        $attributes .= self::build_search_attribute($row, $search['columns']);
                    }
                    echo '<div class="reactdb-item"' . $attributes . '>' . $content . '</div>';
                }
            } else {
                echo '<ul class="reactdb-output-list">';
                foreach ($rows as $row) {
                    echo '<li>' . esc_html(join(' | ', $row)) . '</li>';
                }
                echo '</ul>';
            }
        }

        if ($interactive) {
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    public static function get_output($task) {
        $config = self::get_task($task);
        if (!$config) {
            return new WP_Error('not_found', 'Task not found', ['status' => 404]);
        }
        if ($config['format'] === 'json') {
            return self::get_rows($config['table']);
        }
        return ['html' => self::render_html($task)];
    }
}
