<?php

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
    $config['images']['filters']['the-filter-tobe-classname']['host-name'] = $_SERVER['HTTP_HOST'];
    $config['images']['filters']['the-filter-tobe-classname']['request-uri'] = $_SERVER['REQUEST_URI'];

    $api_enabled = $config['images']['filters']['the-filter-tobe-classname']['enabled'];
    if ($api_enabled) {
        $phast_config = require_once __DIR__ . '/../vendor/kiboit/phast/src/Environment/config-default.php';
        foreach ($phast_config['images']['filters'] as $filter => $config) {
            $config['images']['filters'][$filter]['enabled'] = false;
        }
    }

    return $config;
}

