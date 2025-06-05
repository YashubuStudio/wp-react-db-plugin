<?php
function reactdb_register_block() {
    $ver = file_exists(dirname(__DIR__) . '/assets/block.js')
        ? filemtime(dirname(__DIR__) . '/assets/block.js')
        : '1.0';

    wp_register_script(
        'reactdb-block',
        plugins_url('assets/block.js', dirname(__DIR__) . '/react-db-plugin.php'),
        ['wp-blocks', 'wp-element', 'wp-components'],
        $ver,
        true
    );

    wp_register_style(
        'reactdb-block-style',
        plugins_url('assets/block.css', dirname(__DIR__) . '/react-db-plugin.php'),
        [],
        $ver
    );

    register_block_type('reactdb/block', [
        'editor_script'   => 'reactdb-block',
        'editor_style'    => 'reactdb-block-style',
        'style'           => 'reactdb-block-style',
        'render_callback' => 'reactdb_block_render',
        'attributes'      => [
            'input' => [
                'type'    => 'string',
                'default' => ''
            ]
        ]
    ]);
}
add_action('init', 'reactdb_register_block');

function reactdb_block_render($atts) {
    return reactdb_shortcode($atts);
}
