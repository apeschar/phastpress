<?php

function phastpress_get_phast_user_config() {
    $plugin_config = phastpress_get_config();

    $phast_config = [
        'servicesUrl' => plugins_url('phast.php', __FILE__)
    ];
    if ($plugin_config['enabled'] === true) {
        $phast_config['switches']['phast'] = true;
    } else if ($plugin_config['enabled'] === false) {
        $phast_config['switches']['phast'] = false;
    }

    $setting2filters = [
        'img-optimization-tags' => ['ImagesOptimizationService\Tags'],
        'img-optimization-css' => ['ImagesOptimizationService\CSS'],
        'css-optimization' => ['CSSInlining', 'CSSOptimization', 'CSSDeferring'],
        'scripts-rearrangement' => ['ScriptsRearrangement'],
        'scripts-defer' => ['ScriptsDeferring'],
        'scripts-proxy' => ['ScriptsProxyService']
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

function phastpress_get_default_config() {
    return [
        'enabled' => false,
        'footer-link' => true,
        'img-optimization-tags' => true,
        'img-optimization-css' => true,
        'css-optimization' => true,
        'scripts-rearrangement' => true,
        'scripts-defer' => true,
        'scripts-proxy' => true
    ];
}

function phastpress_get_config() {
    $user = get_option(PHASTPRESS_SETTINGS_OPTION, []);
    $default = phastpress_get_default_config();
    return array_merge($default, $user);
}
