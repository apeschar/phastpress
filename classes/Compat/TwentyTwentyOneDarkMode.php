<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

use Twenty_Twenty_One_Dark_Mode;
use ReflectionClass;
use Exception;

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

        Log::add('twentytwentyone', 'adding script to set is-dark-theme class early');

        $darkMode = $this->findDarkModeObject();
        if (!$darkMode) {
            Log::add('twentytwentyone', 'could not find hook');
            return;
        }

        try {
            $cls = new ReflectionClass($darkMode);
            $meth = $cls->getMethod('switch_should_render');
            if (!$meth->invoke($darkMode)) {
                Log::add('twentytwentyone', 'switch won\'t render');
                return;
            }
        } catch (Exception $e) {
            Log::add('twentytwentyone', 'unable to call Twenty_Twenty_One_Dark_Mode::switch_should_render; check error log');
            error_log(sprintf(
                'Caught %s while trying to call Twenty_Twenty_One_Dark_Mode::switch_should_render: (%d) %s',
                get_class($e),
                $e->getCode(),
                $e->getMessage()
            ));
        }

        add_filter('wp_body_open', function () {
            echo phastpress_script(file_get_contents(__FILE__ . '.js'), ['data-phast-no-defer' => '']);
        });
    }

    private function findDarkModeObject() {
        foreach ($GLOBALS['wp_filter']['wp_footer'] as $priority => $actions) {
            foreach ($actions as $action) {
                if (is_array($action['function'])
                    && sizeof($action['function']) == 2
                    && $action['function'][0] instanceof Twenty_Twenty_One_Dark_Mode
                ) {
                    return $action['function'][0];
                }
            }
        }
        return null;
    }
}
