<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class BurstStatistics {
    public function setup() {
        if (!class_exists('burst_statistics')) {
            return;
        }

        add_filter('wp_enqueue_scripts', function () {
            wp_scripts()->add_data('burst', 'phast_no_defer', true);
            wp_scripts()->add_data('burst-timeme', 'phast_no_defer', true);
        });
    }
}
