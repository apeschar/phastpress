<?php

define('PHASTPRESS_SETTINGS_OPTION', 'phastpress-settings');
define('PHASTPRESS_NONCE_NAME', 'phastpress-nonce');
define('PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG', 'phastpress-activated');
define('PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG', 'phastpress-configured');

register_activation_hook(PHASTPRESS_PLUGIN_FILE, function () {
    require_once __DIR__ . '/functions.php';
    phastpress_set_activation_config();
});

add_action('wp_ajax_phastpress_dismiss_notice', function () {
    update_option(PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG, false);
});

add_action('wp_ajax_phastpress_save_config', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_save_config();
    wp_send_json(phastpress_get_admin_panel_data());
});

add_action('wp_ajax_phastpress_get_admin_panel_data', function () {
    require_once __DIR__ . '/functions.php';
    wp_send_json(phastpress_get_admin_panel_data());
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

add_action('plugins_loaded', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_deploy();
});

add_action('update_option_admin_email', function () {
    require_once __DIR__ . '/functions.php';
    phastpress_update_admin_email();
});

add_action('admin_print_scripts', function () {
    require_once __DIR__ . '/functions.php';
    echo phastpress_auto_configure_script();
});

