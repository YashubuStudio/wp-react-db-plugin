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

function reactdb_app_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please log in to view this page.</p>';
    }

    // Render container for the React app on the front-end
    ob_start();
    echo '<div id="root"></div>';

    $ver = file_exists(dirname(__DIR__) . '/assets/app.js') ? filemtime(dirname(__DIR__) . '/assets/app.js') : '1.0';
    wp_enqueue_script(
        'react-db-plugin-script',
        plugins_url('assets/app.js', dirname(__DIR__) . '/react-db-plugin.php'),
        [],
        $ver,
        true
    );
    wp_enqueue_style(
        'react-db-plugin-style',
        plugins_url('assets/app.css', dirname(__DIR__) . '/react-db-plugin.php'),
        [],
        $ver
    );
    $user = wp_get_current_user();
    wp_localize_script('react-db-plugin-script', 'ReactDbGlobals', [
        'isPlugin'    => true,
        'currentUser' => $user->display_name,
        'logoutUrl'   => wp_logout_url(),
        'nonce'       => wp_create_nonce('wp_rest')
    ]);

    return ob_get_clean();
}
add_shortcode('reactdb_app', 'reactdb_app_shortcode');
