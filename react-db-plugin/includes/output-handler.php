<?php
class OutputHandler {
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
        $dateField = isset($config['dateField']) ? $config['dateField'] : '';
        $categoryField = isset($config['categoryField']) ? $config['categoryField'] : '';
        $firstRow = isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
        $hasDate = $dateField && array_key_exists($dateField, $firstRow);
        $hasCategory = $categoryField && array_key_exists($categoryField, $firstRow);
        $hasFilters = $hasDate || $hasCategory;
        $dateValues = [];
        if ($hasDate) {
            foreach ($rows as $row) {
                if (!is_array($row) || !array_key_exists($dateField, $row)) {
                    continue;
                }
                $value = (string) $row[$dateField];
                if ($value === '') {
                    continue;
                }
                $dateValues[$value] = true;
            }
            $dateValues = array_keys($dateValues);
        }
        $categoryValues = [];
        if ($hasCategory) {
            foreach ($rows as $row) {
                if (!is_array($row) || !array_key_exists($categoryField, $row)) {
                    continue;
                }
                $value = (string) $row[$categoryField];
                if ($value === '') {
                    continue;
                }
                $categoryValues[$value] = true;
            }
            $categoryValues = array_keys($categoryValues);
        }
        $containerId = 'reactdb-output-' . uniqid();
        $asset_dir = plugin_dir_path(__FILE__) . '../assets/';
        $plugin_file = dirname(__DIR__) . '/react-db-plugin.php';
        $css_path = $asset_dir . 'output-tabs.css';
        $js_path = $asset_dir . 'output-tabs.js';
        $css_version = file_exists($css_path) ? filemtime($css_path) : false;
        $js_version = file_exists($js_path) ? filemtime($js_path) : false;

        ob_start();
        if ($hasFilters) {
            $css_url = plugins_url('assets/output-tabs.css', $plugin_file);
            $js_url = plugins_url('assets/output-tabs.js', $plugin_file);
            wp_enqueue_style('reactdb-output-tabs', $css_url, [], $css_version);
            wp_enqueue_script('reactdb-output-tabs', $js_url, [], $js_version, true);
        }
        if ($css !== '') {
            echo '<style>' . $css . '</style>';
        }

        if ($hasFilters) {
            echo '<div class="reactdb-tabbed-output" data-reactdb-tabbed-output="1" id="' . esc_attr($containerId) . '" data-reactdb-task="' . esc_attr($task) . '">';
            if ($hasDate) {
                echo '<div class="reactdb-tab-group reactdb-tab-group-date" data-filter="date">';
                echo '<div class="reactdb-tab-title">日付</div>';
                echo '<div class="reactdb-tab-list">';
                echo '<button type="button" class="reactdb-tab-button is-active" data-value="" data-default="1">すべて</button>';
                foreach ($dateValues as $value) {
                    echo '<button type="button" class="reactdb-tab-button" data-value="' . esc_attr($value) . '">' . esc_html($value) . '</button>';
                }
                echo '</div>';
                echo '</div>';
            }
            if ($hasCategory) {
                echo '<div class="reactdb-tab-group reactdb-tab-group-category" data-filter="category">';
                echo '<div class="reactdb-tab-title">カテゴリ</div>';
                echo '<div class="reactdb-tab-list">';
                echo '<button type="button" class="reactdb-tab-button is-active" data-value="" data-default="1">すべて</button>';
                foreach ($categoryValues as $value) {
                    echo '<button type="button" class="reactdb-tab-button" data-value="' . esc_attr($value) . '">' . esc_html($value) . '</button>';
                }
                echo '</div>';
                echo '</div>';
            }
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
                if ($hasFilters) {
                    $dateValue = $hasDate && array_key_exists($dateField, $row) ? (string) $row[$dateField] : '';
                    $categoryValue = $hasCategory && array_key_exists($categoryField, $row) ? (string) $row[$categoryField] : '';
                    echo '<div class="reactdb-item"' . ($hasDate ? ' data-date="' . esc_attr($dateValue) . '"' : '')
                        . ($hasCategory ? ' data-category="' . esc_attr($categoryValue) . '"' : '') . '>' . $html . '</div>';
                } else {
                    echo $html;
                }
            }
        } else {
            if ($hasFilters) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $dateValue = $hasDate && array_key_exists($dateField, $row) ? (string) $row[$dateField] : '';
                    $categoryValue = $hasCategory && array_key_exists($categoryField, $row) ? (string) $row[$categoryField] : '';
                    $content = '<div class="reactdb-default-row">' . esc_html(join(' | ', $row)) . '</div>';
                    echo '<div class="reactdb-item"' . ($hasDate ? ' data-date="' . esc_attr($dateValue) . '"' : '')
                        . ($hasCategory ? ' data-category="' . esc_attr($categoryValue) . '"' : '') . '>' . $content . '</div>';
                }
            } else {
                echo '<ul class="reactdb-output-list">';
                foreach ($rows as $row) {
                    echo '<li>' . esc_html(join(' | ', $row)) . '</li>';
                }
                echo '</ul>';
            }
        }

        if ($hasFilters) {
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
