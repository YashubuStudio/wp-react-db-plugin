<?php
class LogHandler {
    public static function addLog($user_id, $action, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'reactdb_logs';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'action' => $action,
            'description' => $description,
            'created_at' => current_time('mysql')
        ]);
    }
}
