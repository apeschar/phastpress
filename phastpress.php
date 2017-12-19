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

require_once __DIR__ . '/vendor/autoload.php';

call_user_func(function () {
    if (is_admin()) {
        return;
    }

    $config = require_once __DIR__ . '/vendor/kiboit/phast/src/config-default.php';
    $config['servicesUrl'] = plugins_url('phast.php', __FILE__);

    \Kibo\Phast\PhastDocumentFilters::deploy($config);
});

add_action('admin_menu', function () {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_diagnostics'
    );

}, 0);

function phastpress_render_diagnostics() {
    $config = require_once __DIR__ . '/vendor/kiboit/phast/src/config-default.php';
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
