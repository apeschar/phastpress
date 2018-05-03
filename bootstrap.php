<?php

define('PHASTPRESS_SETTINGS_OPTION', 'phastpress-settings');
define('PHASTPRESS_NONCE_NAME', 'phastpress-nonce');
define('PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG', 'phastpress-activated');
define('PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG', 'phastpress-configured');

register_activation_hook(__DIR__ . '/phastpress.php', function () {
    update_option(PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG, true);
    update_option(PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG, true);
});

add_action('wp_ajax_phastpress_dismiss_notice', function () {
    update_option(PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG, false);
});

add_action('admin_notices', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_render_plugin_install_notice();
});


add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    require_once __DIR__ . '/functions.php';
    array_unshift($links, phastpress_get_settings_link());
    return $links;
});

add_action('admin_menu', function () {
    require_once __DIR__ . '/functions.php';
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );
}, 0);

add_action('admin_init', function () {
    wp_register_style('phastpress-styles', plugins_url('admin-style.css', __FILE__), [], '0.1');
});


add_action('plugins_loaded', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_deploy();
});

add_action('update_option_admin_email', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_update_admin_email();
});


