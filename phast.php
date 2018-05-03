<?php

require_once __DIR__ . '/functions/service.php';

\Kibo\Phast\PhastServices::serve(function () {
    $service_config = phastpress_get_service_config();
    require_once $service_config['wp_includes_dir'] . '/class-requests.php';
    \Requests::register_autoloader();
    \Requests::set_certificate_path($service_config['wp_includes_dir'] . '/certificates/ca-bundle.crt');
    return phastpress_get_service_config();
});
