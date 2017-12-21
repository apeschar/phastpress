<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
\Kibo\Phast\PhastServices::serve(function () {
    return phastpress_get_service_config();
});
