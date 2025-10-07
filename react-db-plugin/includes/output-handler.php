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
        if (!empty($config['html'])) {
            ob_start();
            if ($css !== '') {
                echo '<style>' . $css . '</style>';
            }
            foreach ($rows as $row) {
                $html = $config['html'];
                foreach ($row as $k => $v) {
                    $html = str_replace('{{' . $k . '}}', esc_html($v), $html);
                }
                echo $html;
            }
            return ob_get_clean();
        }
        ob_start();
        if ($css !== '') {
            echo '<style>' . $css . '</style>';
        }
        echo '<ul class="reactdb-output-list">';
        foreach ($rows as $row) {
            echo '<li>' . esc_html(join(' | ', $row)) . '</li>';
        }
        echo '</ul>';
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
