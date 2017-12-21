<?php

function phastpress_get_phast_user_config() {
    return [
        'servicesUrl' => plugins_url('phast.php', __FILE__)
    ];
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
