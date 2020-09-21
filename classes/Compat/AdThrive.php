<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class AdThrive {
    public function setup() {
        add_action('template_redirect', function () {
            $this->template_redirect();
        });
    }

    private function template_redirect() {
        if (!class_exists(\AdThrive_Ads\Components\Ads\Main::class)) {
            return;
        }

        if (!isset($GLOBALS['wp_filter']['wp_head'])) {
            return;
        }

        $filter = $GLOBALS['wp_filter']['wp_head'];

        array_walk($filter->callbacks, function (&$callbacks) {
            array_walk($callbacks, function (&$callback) {
                $function = $callback['function'];
                if (is_array($function)
                    && isset($function[0])
                    && $function[0] instanceof \AdThrive_Ads\Components\Ads\Main
                    && isset($function[1])
                    && $function[1] === 'ad_head'
                ) {
                    $callback['function'] = function (...$args) use ($function) {
                        ob_start(function ($buffer) {
                            return $this->processOutput($buffer);
                        });
                        try {
                            return call_user_func_array($function, $args);
                        } finally {
                            ob_end_flush();
                        }
                    };
                }
            });
        });
    }

    private function processOutput($output) {
        return preg_replace('~<script\b~i', '$0 data-phast-no-defer', $output);
    }
}
