<?php
/*
Plugin Name: PhastPress
Description: PhastPress automatically optimizes your site for the best possible Google PageSpeed Insights score.
Version: 1.15
Author: Kibo IT
Author URI: https://kiboit.com
License: AGPLv3
*/

define('PHASTPRESS_VERSION', '1.15');
define('PHASTPRESS_PLUGIN_FILE', __FILE__);

require_once dirname(__FILE__) . '/sdk/Phast_Plugins_Bootstrap.php';

Phast_Plugins_Bootstrap::boot(
    dirname(__FILE__) . '/bootstrap.php',
    dirname(__FILE__) . '/low-php-version.php'
);
