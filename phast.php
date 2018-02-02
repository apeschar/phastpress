<?php

require_once __DIR__ . '/functions/service.php';

\Kibo\Phast\PhastServices::serve(function () {
    return phastpress_get_service_config();
});
