<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class Log {
    private static $messages;

    public static function setup() {
        add_action('wp_head', __CLASS__ . '::output');
    }

    public static function add($plugin, $message) {
        self::$messages[$plugin][] = $message;
    }

    public static function output() {
        if (empty(self::$messages)) {
            return;
        }

        $o = 'console.group("[PhastPress] Plugin compatibility");';
        foreach (self::$messages as $plugin => $messages) {
            foreach ($messages as $message) {
                $o .= 'console.log(' . json_encode($plugin ? "{$plugin}: {$message}" : $message) . ');';
            }
        }
        $o .= 'console.groupEnd();';

        echo phastpress_script($o, ['data-phast-no-defer' => '']);
    }
}
