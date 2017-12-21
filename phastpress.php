<?php
/*
Plugin Name: PhastPress
Plugin URI: https://phast.io/wp
Description: PhastPress is an automated page optimisation plugin for WordPress.
Version: 0.1
Author: Kibo IT
Author URI: https://kiboit.com
License: Proprietary
*/

function phastpress_get_user_config() {
    return [
        'servicesUrl' => plugins_url('phast.php', __FILE__)
    ];
}

call_user_func(function () {
    if (is_admin()) {
        return;
    }
    require_once __DIR__ . '/vendor/autoload.php';

    \Kibo\Phast\PhastDocumentFilters::deploy(phastpress_get_user_config());
});

add_action('admin_menu', function () {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );

}, 0);

function phastpress_render_settings() {
    require_once __DIR__ . '/vendor/autoload.php';

    wp_enqueue_style('phastpress-styles', plugins_url('admin-style.css', __FILE__), [], '0.1');


    $config = phastpress_get_user_config();
    $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
    $groups = [];
    foreach ($diagnostics->run($config) as $status) {
        $type = $status->getPackage()->getType();
        if (!isset ($groups[$type])) {
            $groups[$type] = [];
        }
        $groups[$type][] = $status;
    }
    include __DIR__ . '/templates/main.php';
}
