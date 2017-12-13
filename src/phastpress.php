<?php
/*
Plugin Name: PhastPress
Plugin URI: https://phast.io/wp
Description: PhastPress is an automated page optimisation plugin for WordPress.
Version: 0.1
Author: Kibo IT
Author URI: https://kiboit.com
License: Proprietary
*/

require_once __DIR__ . '/vendor/autoload.php';
if (!is_admin()) {
    $config = require_once __DIR__ . '/vendor/kiboit/phast/src/config-default.php';
    $config['servicesUrl'] = plugins_url('phast.php', __FILE__);
    \Kibo\Phast\PhastDocumentFilters::deploy($config);
}
