<?php

require_once __DIR__ . '/functions/service.php';
require_once __DIR__ . '/sdk/Phast_Plugins_Bootstrap.php';

Phast_Plugins_Bootstrap::registerAutoloader();
phastpress_register_autoloader();
phastpress_get_service_sdk()->getServiceAPI()->serve();
