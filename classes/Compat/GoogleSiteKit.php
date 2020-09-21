<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class GoogleSiteKit {
    public function setup() {
        // Don't delay Google Site Kit Analytics.
        add_filter('wp_print_scripts', function () {
            wp_scripts()->add_data('google_gtagjs', 'phast_no_defer', true);
        });
    }
}
