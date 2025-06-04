<?php
function reactdb_shortcode($atts) {
    global $wpdb;
    $atts = shortcode_atts([
        'db' => '',
        'data' => ''
    ], $atts);

    $table = $atts['db'] ? $wpdb->prefix . $atts['db'] : '';
    $result = [];
    if ($table) {
        $result = $wpdb->get_row("SELECT * FROM {$table} LIMIT 1", ARRAY_A);
    }
    if ($result) {
        return '<pre>' . esc_html(print_r($result, true)) . '</pre>';
    }
    return '<div>No data</div>';
}
add_shortcode('reactdb', 'reactdb_shortcode');
