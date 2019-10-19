<?php
namespace Kibo\PhastPlugins\PhastPress;

require_once __DIR__ . '/autoload.php';

WordPress::loadConfig();
WordPress::loadRequests();

phastpress_get_service_sdk()->getServiceAPI()->serve();
