<?php
class LogHandler {
    public static function addLog($user_id, $action, $description) {
        global $wpdb;
        $table = $wpdb->prefix . 'reactdb_logs';
        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM $table WHERE user_id = %d AND action = %s ORDER BY created_at DESC LIMIT 1",
            $user_id,
            $action
        ));

        $should_insert = true;
        if ($last) {
            $last_ts = strtotime($last);
            if ($last_ts && $last_ts > current_time('timestamp') - 600) {
                $should_insert = false;
            }
        }

        if ($should_insert) {
            $wpdb->insert($table, [
                'user_id'    => $user_id,
                'action'     => $action,
                'description'=> $description,
                'created_at' => current_time('mysql')
            ]);
        }
    }
}
