<?php
function reactdb_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts([
        'db'    => '',
        'data'  => '',
        'input' => ''
    ], $atts);

    // Parse `input` attribute formatted as DB:"table",data
    if (!empty($atts['input']) && preg_match('/DB:"([^"]+)"\s*,\s*(.+)/', $atts['input'], $m)) {
        $atts['db']   = $m[1];
        $atts['data'] = $m[2];
    }

    $table  = $atts['db'] ? $wpdb->prefix . $atts['db'] : '';
    $result = [];

    if ($table) {
        $result = $wpdb->get_row("SELECT * FROM {$table} LIMIT 1", ARRAY_A);
    }

    if ($result) {
        $content  = '<pre>' . esc_html(print_r($result, true)) . '</pre>';
    } else {
        $content  = '<div>No data</div>';
    }

    return '<div class="reactdb-block">' . $content . '</div>';
}
add_shortcode('reactdb', 'reactdb_shortcode');
