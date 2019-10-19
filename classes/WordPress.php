<?php
namespace Kibo\PhastPlugins\PhastPress;

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
            require self::findWPConfig();
        } catch (WordPressLoadedException $e) {
            return;
        } finally {
            $complete = true;
        }
        throw new \RuntimeException("WordPress loaded without triggering any hooks");
    }

    private static function findWPConfig() {
        $startDir = dirname($_SERVER['SCRIPT_FILENAME']);
        if (!$startDir) {
            throw new \RuntimeException("Could not get directory from SCRIPT_FILENAME");
        }
        $dir = $startDir;
        while (true) {
            $path = $dir . '/wp-config.php';
            if (file_exists($path)) {
                return $path;
            }
            $parent = dirname($dir);
            if ($parent == $dir) {
                break;
            }
            $dir = $parent;
        }
        throw new \RuntimeException("Could not find wp-config.php in $startDir " .
                                    "or any of its parent directories");
    }

    public static function loadRequests() {
        if (!class_exists('Requests')) {
            require ABSPATH . WPINC . '/http.php';
        }
    }

}

class WordPressLoadedException extends \Exception {}
