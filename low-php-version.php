<?php

add_action('admin_menu', 'phastpress_register_menu', 0);

function phastpress_register_menu() {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );
}

function phastpress_render_settings() {
    wp_enqueue_style('phastpress-styles', plugins_url('admin-style.css', __FILE__), array(), '0.1');
    include dirname(__FILE__) . '/templates/low-php-version.php';
}
