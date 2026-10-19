<?php
defined('ABSPATH') || exit;

/** Return only tables owned by this plugin for the current WordPress site. */
function reactdb_get_managed_tables() {
    global $wpdb;

    $prefix = $wpdb->prefix . 'reactdb_';
    $pattern = $wpdb->esc_like($prefix) . '%';
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));

    if (!is_array($tables)) {
        return [];
    }

    return array_values(array_filter($tables, function ($table) use ($prefix) {
        return is_string($table) && strpos($table, $prefix) === 0;
    }));
}

/** Drop a plugin-owned table and verify that it is gone. */
function reactdb_drop_managed_table($name) {
    global $wpdb;

    $name = sanitize_key($name);
    if ($name === '') {
        return new WP_Error('invalid_table_name', 'テーブル名が空か不正です。', ['status' => 400]);
    }

    $prefix = $wpdb->prefix . 'reactdb_';
    $table = $prefix . $name;
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($found !== $table) {
        return new WP_Error('table_not_found', '対象のテーブルが見つかりません。', ['status' => 404]);
    }

    $quoted_table = '`' . str_replace('`', '``', $table) . '`';
    $result = $wpdb->query("DROP TABLE IF EXISTS $quoted_table");
    if ($result === false) {
        $detail = $wpdb->last_error ? $wpdb->last_error : 'データベースが削除を完了できませんでした。';
        return new WP_Error('table_drop_failed', 'テーブルを削除できませんでした: ' . $detail, ['status' => 500]);
    }

    $remaining = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    if ($remaining === $table) {
        return new WP_Error('table_drop_incomplete', '削除処理後もテーブルが残っています。データベース権限を確認してください。', ['status' => 500]);
    }

    return true;
}

/** Remove output tasks that point at a table that has just been dropped. */
function reactdb_remove_output_settings_for_table($name) {
    $snapshot = OutputHandler::read_settings_snapshot();
    if (is_wp_error($snapshot)) {
        return $snapshot;
    }
    $settings = $snapshot['settings'];

    $name = sanitize_key($name);
    $changed = false;
    foreach ($settings as $task => $config) {
        if (is_array($config) && isset($config['table']) && sanitize_key($config['table']) === $name) {
            unset($settings[$task]);
            $changed = true;
        }
    }

    if ($changed) {
        return OutputHandler::store_settings_snapshot($settings, $snapshot['raw']);
    }
    return $settings;
}

/** Remove settings left behind by older versions after their tables disappeared. */
function reactdb_prune_orphaned_output_settings() {
    global $wpdb;

    $snapshot = OutputHandler::read_settings_snapshot();
    if (is_wp_error($snapshot)) {
        return $snapshot;
    }
    $settings = $snapshot['settings'];

    $changed = false;
    foreach ($settings as $task => $config) {
        if (!is_array($config) || empty($config['table'])) {
            unset($settings[$task]);
            $changed = true;
            continue;
        }

        $name = sanitize_key($config['table']);
        if ($name === '') {
            unset($settings[$task]);
            $changed = true;
            continue;
        }

        $table = $wpdb->prefix . 'reactdb_' . $name;
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($wpdb->last_error !== '') {
            // A database query error is not evidence that a referenced table is absent.
            return new WP_Error('output_table_read_failed', '出力元テーブルの確認に失敗しました。設定は削除していません。', ['status' => 500]);
        }
        if ($found !== $table) {
            unset($settings[$task]);
            $changed = true;
        }
    }

    if ($changed) {
        return OutputHandler::store_settings_snapshot($settings, $snapshot['raw']);
    }

    return $settings;
}

/** Clean all plugin-owned tables, options, and the generated app page. */
function reactdb_uninstall_current_site() {
    global $wpdb;

    foreach (reactdb_get_managed_tables() as $table) {
        $quoted_table = '`' . str_replace('`', '``', $table) . '`';
        if ($wpdb->query("DROP TABLE IF EXISTS $quoted_table") === false) {
            error_log('React DB Plugin uninstall: Could not drop table ' . $table . ': ' . $wpdb->last_error);
            continue;
        }

        $remaining = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
        if ($remaining === $table) {
            error_log('React DB Plugin uninstall: Table remains after drop attempt: ' . $table);
        }
    }

    $option_pattern = $wpdb->esc_like('reactdb_') . '%';
    $option_names = $wpdb->get_col($wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
        $option_pattern
    ));
    foreach ((array) $option_names as $option_name) {
        if (!delete_option($option_name) && get_option($option_name, null) !== null) {
            error_log('React DB Plugin uninstall: Could not delete option ' . $option_name);
        }
    }

    $page = get_page_by_path('react-db-app', OBJECT, 'page');
    if ($page && strpos((string) $page->post_content, '[reactdb_app') !== false) {
        if (!wp_delete_post($page->ID, true)) {
            error_log('React DB Plugin uninstall: Could not delete generated page ' . $page->ID);
        }
    }
}
