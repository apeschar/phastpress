<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class GAGoogleAnalytics {
    public function setup() {
        // Don't delay GA Google Analytics script.
        add_filter('ga_google_analytics_script_atts_ext', function ($atts) {
            return $atts . ' data-phast-no-defer';
        });

        add_filter('ga_google_analytics_script_atts', function ($atts) {
            return $atts . ' data-phast-no-defer';
        });
    }
}
