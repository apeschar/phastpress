<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Kibo/PhastPlugins/ImageAPIClient/Factory.php';
require_once __DIR__ . '/../Kibo/PhastPlugins/ImageAPIClient/Filter.php';
require_once __DIR__ . '/../Kibo/PhastPlugins/ImageAPIClient/Diagnostics.php';


function phastpress_get_cache_root_candidates() {
    $key = md5($_SERVER['DOCUMENT_ROOT']) . '.' . posix_geteuid();
    return [
        __DIR__ . '/../cache/' . $key,
        sys_get_temp_dir() . '/phastpress.' . $key
    ];
}

function phastpress_get_cache_root() {
    foreach (phastpress_get_cache_root_candidates() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
        if (@mkdir($dir, 0777, true)) {
            return $dir;
        }
    }
    return false;
}

function phastpress_store_in_php_file($filename, $value) {
    $content = '<?php \'' . addcslashes($value, '\\\'') . '\';';
    return @file_put_contents($filename, $content);
}

function phastpress_read_from_php_file($filename) {
    $content = @file_get_contents($filename);
    if (!$content) {
        return false;
    }
    $matches = [];
    if (!preg_match('/^<\?php \'(.*?)\';$/', $content, $matches)) {
        return false;
    }
    return stripcslashes($matches[1]);
}

function phastpress_get_cache_stored_file_path($filename) {
    $dir = phastpress_get_cache_root();
    if (!$dir) {
        return false;
    }
    return "$dir/$filename.php";
}

function phastpress_get_service_config_filename() {
    return phastpress_get_cache_stored_file_path('service-config');
}

function phastpress_get_service_config() {
    $serialized = phastpress_read_from_php_file(
        phastpress_get_service_config_filename()
    );
    if (!$serialized) {
        return false;
    }
    $config = @unserialize($serialized);
    if (!$config) {
        return false;
    }

    $config['cache'] = ['cacheRoot' => phastpress_get_cache_root()];

    $apiFilterName = Kibo\PhastPlugins\ImageAPIClient\Filter::class;
    $api_enabled = $config['images']['filters'][$apiFilterName]['enabled'];
    if (!$api_enabled) {
        unset ($config['images']['filters'][$apiFilterName]);
        return $config;
    }

    $config['images']['filters'][$apiFilterName]['host-name'] = $_SERVER['HTTP_HOST'];
    $config['images']['filters'][$apiFilterName]['request-uri'] = $_SERVER['REQUEST_URI'];
    $config['images']['filters'][$apiFilterName]['api-url']
        = 'http://phast.test/phast.php?service=images';

    $phast_config = require __DIR__ . '/../vendor/kiboit/phast/src/Environment/config-default.php';
    foreach (array_keys($phast_config['images']['filters']) as $filter) {
        $config['images']['filters'][$filter]['enabled'] = false;
    }
    return $config;
}

