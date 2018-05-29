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

function phastpress_get_plugin_version() {
    $plugin_info = get_file_data(PHASTPRESS_PLUGIN_FILE, array('Version' => 'Version'));
    return $plugin_info['Version'];
}

function phastpress_is_dev() {
    return phastpress_get_plugin_version() === '$VER' . 'SION$';
}

add_action('admin_menu', 'phastpress_register_menu', 0);

function phastpress_register_menu() {

    $plugin_version = phastpress_get_plugin_version();
    if (phastpress_is_dev()) {
        wp_register_script('phastpress-app', "http://localhost:8080/app.js", [], $plugin_version, true);
    } else {
        $static = plugin_dir_url(PHASTPRESS_PLUGIN_FILE) . 'static';
        wp_register_style('phastpress-style', "$static/css/app.css", [], $plugin_version);
        wp_register_script('phastpress-manifest', "$static/js/manifest.js", [], $plugin_version, true);
        wp_register_script('phastpress-vendor', "$static/js/vendor.js", ['phastpress-manifest'], $plugin_version, true);
        wp_register_script(
            'phastpress-app',
            "$static/js/app.js",
            [
                'phastpress-manifest',
                'phastpress-vendor'
            ],
            $plugin_version,
            true
        );
    }

    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );
}

function phastpress_render_settings() {
    wp_enqueue_script('phastpress-app');
    if (!phastpress_is_dev()) {
        wp_enqueue_style('phastpress-style');
    }
    echo '<div id="app"></div>';
}

if (version_compare(PHP_VERSION, '5.6') < 0) {
    require dirname(__FILE__) . '/low-php-version.php';
} else {
    require __DIR__ . '/bootstrap.php';
}
