<?php
namespace Kibo\PhastPlugins\PhastPress;

require_once __DIR__ . '/sdk/phast.php';
require_once __DIR__ . '/functions/api.php';
require_once __DIR__ . '/functions/deployment.php';
require_once __DIR__ . '/functions/service.php';

spl_autoload_register(function ($class) {
    if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen(__NAMESPACE__) + 1);
    $classFile = __DIR__ . '/classes/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($classFile)) {
        include $classFile;
    }
});
