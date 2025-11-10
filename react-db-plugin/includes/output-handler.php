<?php
class OutputHandler {
    private const FILTER_MULTI_SEPARATOR = '|~|';
    public static function get_settings() {
        $settings = get_option('reactdb_output_settings', []);
        return is_array($settings) ? $settings : [];
    }

    public static function get_task($task) {
        $settings = self::get_settings();
        return isset($settings[$task]) ? $settings[$task] : null;
    }

    public static function update_settings($settings) {
        if (!is_array($settings)) {
            return;
        }
        foreach ($settings as $task => $conf) {
            if (isset($conf['html'])) {
                $settings[$task]['html'] = wp_kses_post($conf['html']);
            }
            if (isset($conf['css'])) {
                $settings[$task]['css'] = wp_strip_all_tags(is_string($conf['css']) ? $conf['css'] : '');
            } elseif (!isset($settings[$task]['css'])) {
                $settings[$task]['css'] = '';
            }
            if (isset($conf['filterCss'])) {
                $settings[$task]['filterCss'] = wp_strip_all_tags(is_string($conf['filterCss']) ? $conf['filterCss'] : '');
            } elseif (!isset($settings[$task]['filterCss'])) {
                $settings[$task]['filterCss'] = '';
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
                    $filters[] = [
                        'id' => $id,
                        'label' => $label,
                        'column' => $column,
                        'type' => $type,
                        'dateFormat' => $dateFormat,
                        'delimiter' => $delimiter,
                        'sort' => $sort,
                        'labelTemplate' => $labelTemplate,
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
        }
        update_option('reactdb_output_settings', $settings);
    }

    public static function get_rows($table) {
        global $wpdb;
        $table = $wpdb->prefix . 'reactdb_' . sanitize_key($table);
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return [];
        }
        return $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
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

    public static function render_html($task) {
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
        $hasFilters = !empty($filters);
        $interactive = $hasFilters || $hasSearch;
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
            echo '<div class="reactdb-tabbed-output" data-reactdb-tabbed-output="1" id="' . esc_attr($containerId) . '" data-reactdb-task="' . esc_attr($task) . '">';
            if ($hasSearch || $hasFilters) {
                echo '<div class="reactdb-tabbed-controls">';
                if ($hasSearch) {
                    echo '<div class="reactdb-search"><label class="reactdb-search-label" for="' . esc_attr($containerId) . '-search">';
                    echo '<span class="reactdb-search-title">キーワード検索</span>';
                    echo '<input type="search" class="reactdb-search-input" id="' . esc_attr($containerId) . '-search" placeholder="キーワードで絞り込み" />';
                    echo '</label></div>';
                }
                foreach ($filters as $filter) {
                    if (!isset($filter['key']) || $filter['key'] === '') {
                        continue;
                    }
                    $options = isset($filter['options']) && is_array($filter['options']) ? $filter['options'] : [];
                    echo '<div class="reactdb-tab-group reactdb-tab-group-' . esc_attr($filter['key']) . '" data-filter="' . esc_attr($filter['key']) . '">';
                    echo '<div class="reactdb-tab-title">' . esc_html($filter['label']) . '</div>';
                    echo '<div class="reactdb-tab-list">';
                    echo '<button type="button" class="reactdb-tab-button is-active" data-value="" data-default="1">すべて</button>';
                    foreach ($options as $option) {
                        if (!is_array($option) || !isset($option['value'])) {
                            continue;
                        }
                        $label = isset($option['label']) ? $option['label'] : $option['value'];
                        echo '<button type="button" class="reactdb-tab-button" data-value="' . esc_attr($option['value']) . '">' . esc_html($label) . '</button>';
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
                $html = $config['html'];
                foreach ($row as $k => $v) {
                    $html = str_replace('{{' . $k . '}}', esc_html($v), $html);
                }
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
