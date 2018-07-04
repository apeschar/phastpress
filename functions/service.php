<?php

function phastpress_autoloader($classname) {
    $namespace = 'Kibo\\PhastPlugins\\PhastPress\\';
    if (strpos($classname, $namespace) === 0) {
        $file = __DIR__ . '/../classes/' . str_replace($namespace, '', $classname) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
}

function phastpress_register_autoloader() {
    static $registered_autoloaders = false;
    if (!$registered_autoloaders) {
        spl_autoload_register('phastpress_autoloader');
        $registered_autoloaders = true;
    }
}

function phastpress_get_service_sdk() {
    static $sdk = null;
    phastpress_register_autoloader();
    if (!$sdk) {
        return new Kibo\PhastPlugins\SDK\ServiceSDK(
            new Kibo\PhastPlugins\PhastPress\ServiceSDKImplementation()
        );
    }
    return $sdk;
}

function phastpress_get_plugin_sdk() {
    static $sdk = null;
    phastpress_register_autoloader();
    if (!$sdk) {
        return new Kibo\PhastPlugins\SDK\SDK(
            new Kibo\PhastPlugins\PhastPress\PluginHostImplementation()
        );
    }
    return $sdk;
}
