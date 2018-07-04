<?php

require_once __DIR__ . '/functions/service.php';
require_once __DIR__ . '/functions/deployment.php';

add_action('wp_ajax_phastpress_ajax_dispatch', function () {
    wp_send_json(
        phastpress_get_plugin_sdk()->getAJAXRequestsDispatcher()->dispatch($_POST)
    );
});

add_action('admin_notices', function () {
    echo phastpress_get_plugin_sdk()->getInstallNotice()->render();
});


add_filter('plugin_action_links_' . plugin_basename(PHASTPRESS_PLUGIN_FILE), function ($links) {
    $link = '<a href="' . admin_url('options-general.php?page=phast-press') . '">'
        . __('Settings', 'phastpress') . '</a>';
    array_unshift($links, $link);
    return $links;
});

add_action('plugins_loaded', function () {
    phastpress_deploy();
});

add_action('admin_print_scripts', function () {
    echo phastpress_get_plugin_sdk()->getAutoConfiguration()->renderScript();
});

add_action('admin_menu', function () {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );
});


function phastpress_render_settings() {
    echo sprintf(
        '<div class="wrap">
            <h1 class="wp-heading-inline">%s</h1>
            %s
         </div>
        ',
        __('PhastPress', 'phastpress'),
        phastpress_get_plugin_sdk()->getAdminPanel()->render()
    );
}
