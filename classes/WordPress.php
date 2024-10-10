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

        // If set_error_handler is used in wp-config.php this will be a fallback
        $GLOBALS['wp_filter']['all'][0][] = [
            'function' => function () use (&$complete) {
                if (!$complete && defined('WP_CONTENT_DIR')) {
                    throw new WordPressLoadedException();
                }
            },
            'accepted_args' => 0,
        ];

        // This should trigger as soon as wp-settings.php is loaded
        set_error_handler(function ($errno, $errstr, $errfile) use ($complete) {
            if (!$complete && basename($errfile) === 'wp-settings.php') {
                throw new WordPressLoadedException();
            }
        });

        // Cause an error to be raised when wp-settings.php is loaded
        define('WPINC', 'wp-includes');

        try {
            $wpLoadScript = self::findWPLoad();
            chdir(dirname($wpLoadScript));
            require $wpLoadScript;
            throw new RuntimeException('WordPress loaded without triggering any hooks');
        } catch (WordPressLoadedException $e) {
        } finally {
            $complete = true;
            set_error_handler(null);
        }

        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
        }
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
            $wpPath = $dir . '/wp/wp-load.php';
            if (file_exists($wpPath)) {
                return $wpPath;
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
        if (class_exists(\WpOrg\Requests\Requests::class)) {
            return;
        }
        require ABSPATH . WPINC . '/Requests/src/Autoload.php';
        \WpOrg\Requests\Autoload::register();
        \WpOrg\Requests\Requests::set_certificate_path(ABSPATH . WPINC . '/certificates/ca-bundle.crt');
    }
}

class WordPressLoadedException extends Exception {
}
