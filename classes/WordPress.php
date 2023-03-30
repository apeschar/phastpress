<?php
namespace Kibo\PhastPlugins\PhastPress;

use Exception;
use Requests;
use RuntimeException;

class WordPress {
    public static function loadConfig() {
        if (defined('ABSPATH')) {
            return;
        }
        $complete = false;
        $hook = function () use (&$complete) {
            if (!$complete && defined('WP_CONTENT_DIR')) {
                throw new WordPressLoadedException();
            }
        };
        $GLOBALS['wp_filter']['all'][0][] = [
            'function' => $hook,
            'accepted_args' => 0,
        ];
        try {
            require self::findWPLoad();
        } catch (WordPressLoadedException $e) {
            return;
        } finally {
            $complete = true;
        }
        throw new RuntimeException('WordPress loaded without triggering any hooks');
    }

    private static function findWPLoad() {
        $startDir = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!$startDir) {
            throw new RuntimeException('Could not get directory from SCRIPT_FILENAME');
        }
        $dir = $startDir;
        while (true) {
            $path = $dir . '/wp-load.php';
            if (file_exists($path)) {
                return $path;
            }
            $parent = dirname($dir);
            if ($parent == $dir) {
                break;
            }
            $dir = $parent;
        }
        throw new RuntimeException(
            "Could not find wp-load.php in $startDir or any of its parent directories"
        );
    }

    public static function loadRequests() {
        if (!defined('REQUESTS_SILENCE_PSR0_DEPRECATIONS')) {
            define('REQUESTS_SILENCE_PSR0_DEPRECATIONS', true);
        }
        if (!class_exists(Requests::class)) {
            require ABSPATH . WPINC . '/class-requests.php';
            Requests::register_autoloader();
            Requests::set_certificate_path(ABSPATH . WPINC . '/certificates/ca-bundle.crt');
        }
    }
}

class WordPressLoadedException extends Exception {
}
