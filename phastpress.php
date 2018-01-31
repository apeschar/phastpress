<?php
/*
Plugin Name: PhastPress
Description: PhastPress automatically optimizes your site for the best possible Google PageSpeed Insights score.
Version: $VERSION$
Author: Kibo IT
Author URI: https://kiboit.com
License: AGPLv3
*/


if (version_compare(PHP_VERSION, '5.6') < 0) {
    require dirname(__FILE__) . '/low-php-version.php';
} else {
    require __DIR__ . '/bootstrap.php';
}
