<?php

function phastpress_get_default_config() {
    return [
        'enabled' => 'admin',
        'footer-link' => false,
        'img-optimization-tags' => true,
        'img-optimization-css' => true,
        'img-optimization-api' => false,
        'css-optimization' => true,
        'scripts-rearrangement' => true,
        'scripts-defer' => true,
        'scripts-proxy' => true,
        'iframe-defer' => true,
    ];
}

function phastpress_get_config() {
    $user = get_option(PHASTPRESS_SETTINGS_OPTION, []);
    $default = phastpress_get_default_config();
    return array_merge($default, $user);
}

function phastpress_save_config() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    $keys = array_keys(phastpress_get_default_config());
    $settings = phastpress_get_config();
    foreach ($keys as $key) {
        $post_key = "phastpress-$key";
        if (!isset($_POST[$post_key])) {
            continue;
        }
        if ($_POST[$post_key] == 'on') {
            $settings[$key] = true;
        } else if ($_POST[$post_key] == 'off') {
            $settings[$key] = false;
        } else if ($_POST[$post_key] == 'admin') {
            $settings[$key] = 'admin';
        }
    }
    update_option(PHASTPRESS_SETTINGS_OPTION, $settings);
}

function phastpress_reset_config() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    delete_option(PHASTPRESS_SETTINGS_OPTION);
}

function phastpress_get_phast_user_config() {
    $plugin_config = phastpress_get_config();

    $phast_config = array_merge(
        ['servicesUrl' => plugins_url('phast.php', __DIR__ . '/../phastpress.php')],
        phastpress_get_service_config()
    );
    if ($plugin_config['enabled'] === true) {
        $phast_config['switches']['phast'] = true;
    } else if ($plugin_config['enabled'] === false) {
        $phast_config['switches']['phast'] = false;
    } else {
        $phast_config['switches']['phast'] = current_user_can('administrator');
    }

    $setting2filters = [
        'img-optimization-tags' => ['ImagesOptimizationService\Tags'],
        'img-optimization-css' => ['ImagesOptimizationService\CSS'],
        'css-optimization' => ['CSSInlining', 'CSSDeferring'],
        'scripts-rearrangement' => ['ScriptsRearrangement'],
        'scripts-defer' => ['ScriptsDeferring'],
        'scripts-proxy' => ['ScriptsProxyService'],
        'iframe-defer' => ['DelayedIFrameLoading']
    ];

    $phast_config['documents']['filters'] = [];
    $phast_filters = &$phast_config['documents']['filters'];
    foreach ($setting2filters as $setting => $filters) {
        foreach ($filters as $filter) {
            $fullFilter = "Kibo\Phast\Filters\HTML\\$filter\Filter";
            $phast_filters[$fullFilter] = ['enabled' => $plugin_config[$setting]];
        }
    }
    return $phast_config;
}
