<?php
/*
Plugin Name: PhastPress
Description: PhastPress automatically optimizes your site for the best possible Google PageSpeed Insights score.
Version: $VERSION$
Author: Kibo IT
Author URI: https://kiboit.com
License: AGPLv3
*/


define('PHASTPRESS_PLUGIN_FILE', __FILE__);

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
    wp_enqueue_script('phastpress-app', 'http://localhost:8080/app.js');
    echo '<div id="app"></div>';
}

if (version_compare(PHP_VERSION, '5.6') < 0) {
    require dirname(__FILE__) . '/low-php-version.php';
} else {
    require __DIR__ . '/bootstrap.php';
}
