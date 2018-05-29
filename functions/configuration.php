<?php

function phastpress_get_default_config() {
    return [
        'enabled' => true,
        'admin-only' => true,
        'pathinfo-query-format' => true,
        'footer-link' => false,
        'img-optimization-tags' => true,
        'img-optimization-css' => true,
        'img-optimization-api' => true,
        'css-optimization' => true,
        'scripts-rearrangement' => false,
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

function phastpress_set_activation_config() {
    update_option(PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG, true);
    update_option(PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG, true);
    $config = phastpress_get_config();
    $config['enabled'] = phastpress_has_cache_root() && phastpress_has_service_config();
    $config['pathinfo-query-format'] = false;
    update_option(PHASTPRESS_SETTINGS_OPTION, $config);
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
        }
    }
    update_option(PHASTPRESS_SETTINGS_OPTION, $settings);
    update_option(PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG, false);
    phastpress_generate_service_config();
}

function phastpress_auto_configure_script() {
    if (!get_option(PHASTPRESS_ACTIVATION_AUTO_CONFIGURATION_FLAG, false)) {
        return;
    }
    $image_url = plugins_url('Kibo/PhastPlugins/ImageAPIClient/kibo-logo.png', PHASTPRESS_PLUGIN_FILE);
    $config = \Kibo\Phast\Environment\Configuration::fromDefaults()
        ->withUserConfiguration(
            new \Kibo\Phast\Environment\Configuration(
                phastpress_get_phast_user_config()
            )
        )
        ->getRuntimeConfig()
        ->toArray();
    $signature = (new \Kibo\Phast\Security\ServiceSignatureFactory())->make($config);
    $service_image_url = (new \Kibo\Phast\Services\ServiceRequest())
        ->withUrl(
            \Kibo\Phast\ValueObjects\URL::fromString(
                plugins_url('phast.php', PHASTPRESS_PLUGIN_FILE)
            )
        )
        ->withParams(['service' => 'images', 'src' => $image_url])
        ->sign($signature)
        ->serialize(\Kibo\Phast\Services\ServiceRequest::FORMAT_PATH);
    $nonce = wp_create_nonce(PHASTPRESS_NONCE_NAME);


    return '<script>(function (imageUrl, nonce) {'
        . file_get_contents(__DIR__ . '/phastpress-auto-config.js')
        . "})('$service_image_url', '$nonce')</script>";
}

function phastpress_generate_service_config() {
    $plugin_config = phastpress_get_config();
    $plugin_version = phastpress_get_plugin_version();
    $config = [
        'plugin_version' => $plugin_version,
        'wp_includes_dir' => ABSPATH . '/' . WPINC,
        'servicesUrl' => plugins_url('phast.php', PHASTPRESS_PLUGIN_FILE),
        'securityToken' => \Kibo\Phast\Security\ServiceSignature::generateToken(),
        'images' => [
            'filters' => [
                \Kibo\PhastPlugins\ImageAPIClient\Filter::class => [
                    'enabled' => $plugin_config['img-optimization-api'],
                    'admin-email' => get_bloginfo('admin_email'),
                    'plugin-version' => $plugin_version
                ]
            ]
        ]
    ];
    return phastpress_store_in_php_file(
        phastpress_get_service_config_filename(),
        serialize($config)
    );
}

function phastpress_generate_service_config_if_not_exists() {
    $filename = phastpress_get_service_config_filename();
    if (!@file_exists($filename)) {
        if (phastpress_generate_service_config()) {
            return phastpress_get_service_config();
        }
        return false;
    }

    $config = phastpress_get_service_config();
    if (empty($config['plugin_version'])
        || $config['plugin_version'] != phastpress_get_plugin_version()
    ) {
        if (phastpress_generate_service_config()) {
            return phastpress_get_service_config();
        }
        return false;
    }

    return $config;
}

function phastpress_get_phast_user_config() {

    $phast_config = phastpress_generate_service_config_if_not_exists();

    $plugin_config = phastpress_get_config();
    $phast_config['switches']['phast'] = phastpress_should_deploy_filters($plugin_config);

    $setting2filters = [
        'img-optimization-tags' => ['ImagesOptimizationService\Tags'],
        'img-optimization-css' => ['ImagesOptimizationService\CSS'],
        'css-optimization' => ['CSSInlining'],
        'scripts-rearrangement' => ['ScriptsRearrangement'],
        'scripts-defer' => ['ScriptsDeferring'],
        'scripts-proxy' => ['ScriptsProxyService'],
        'iframe-defer' => ['DelayedIFrameLoading']
    ];

    $phast_config['documents']['filters'] = [];
    $phast_filters = &$phast_config['documents']['filters'];
    $phast_switches = &$phast_config['switches'];
    foreach ($setting2filters as $setting => $filters) {
        foreach ($filters as $filter) {
            $fullFilter = "Kibo\Phast\Filters\HTML\\$filter\Filter";
            $phast_filters[$fullFilter] = ['enabled' => $setting];
            $phast_switches[$setting] = $plugin_config[$setting];
        }
    }

    if ($plugin_config['pathinfo-query-format']) {
        $phast_config['serviceRequestFormat'] = \Kibo\Phast\Services\ServiceRequest::FORMAT_PATH;
    } else {
        $phast_config['serviceRequestFormat'] = \Kibo\Phast\Services\ServiceRequest::FORMAT_QUERY;
    }

    return $phast_config;
}

function phastpress_should_deploy_filters(array $plugin_config) {
    if (!$plugin_config['enabled']) {
        return false;
    }
    if (!$plugin_config['admin-only']) {
        return true;
    }
    return current_user_can('administrator');
}

function phastpress_update_admin_email() {
    $config = phastpress_get_service_config();
    $config['images']['filters'][\Kibo\PhastPlugins\ImageAPIClient\Filter::class]['admin-email']
        = get_bloginfo('admin_email');
    phastpress_store_in_php_file(
        phastpress_get_service_config_filename(),
        serialize($config)
    );
}
