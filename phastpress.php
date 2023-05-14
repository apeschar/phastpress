<?php
/*
Plugin Name: PhastPress
Description: PhastPress automatically optimizes your site for the best possible Google PageSpeed Insights score.
Version: 2.16
Author: Albert Peschar
Author URI: https://kiboit.com
License: AGPLv3
*/

define('PHASTPRESS_VERSION', '2.16');
define('PHASTPRESS_PLUGIN_FILE', __FILE__);

if (version_compare(PHP_VERSION, '7.3', '<')) {
    require dirname(__FILE__) . '/low-php-version.php';
} else {
    require dirname(__FILE__) . '/bootstrap.php';
}
