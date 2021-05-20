<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

use Twenty_Twenty_One_Dark_Mode;

class TwentyTwentyOneDarkMode {
    public function setup() {
        add_action('after_setup_theme', function () {
            $this->afterSetupTheme();
        }, 20);
    }

    private function afterSetupTheme() {
        if (!class_exists(Twenty_Twenty_One_Dark_Mode::class)) {
            return;
        }

        Log::add('twentytwentyone', 'wrapping dark switch hook to add data-phast-no-defer and prevent CSS optimization');

        $action = $this->findAction();
        if (!$action) {
            Log::add('twentytwentyone', 'could not find hook');
            return;
        }

        if (!remove_filter('wp_footer', $action['function'], $action['priority'])) {
            Log::add('twentytwentyone', 'could not remove hook');
            return;
        }

        add_filter('wp_footer', function () use ($action) {
            ob_start(function ($buffer) {
                if (trim($buffer) == '') {
                    return $buffer;
                }
                return
                    str_replace('<script>', '<script data-phast-no-defer>', $buffer) .
                    '<div class="is-dark-theme"></div>';
            });
            try {
                $action['function']();
            } finally {
                ob_end_flush();
            }
        }, $action['priority']);
    }

    private function findAction() {
        foreach ($GLOBALS['wp_filter']['wp_footer'] as $priority => $actions) {
            foreach ($actions as $action) {
                if (is_array($action['function'])
                    && sizeof($action['function']) == 2
                    && $action['function'][0] instanceof Twenty_Twenty_One_Dark_Mode
                    && $action['function'][1] == 'the_switch'
                ) {
                    return [
                        'priority' => $priority,
                        'function' => $action['function'],
                    ];
                }
            }
        }
        return null;
    }
}
