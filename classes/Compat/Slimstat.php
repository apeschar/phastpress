<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class Slimstat {
    public function setup() {
        // Don't delay Slimstat Analytics.
        add_filter('wp_print_scripts', function () {
            if (!wp_script_is('wp_slimstat')) {
                return;
            }

            // Don't defer the tracker script.
            add_filter('script_loader_tag', function ($tag, $handle, $src) {
                if ($handle !== 'wp_slimstat') {
                    return $tag;
                }
                return preg_replace('~<script\b~', '$0 data-phast-no-defer async', $tag);
            }, 10, 3);

            // Don't defer the inline parameters script.
            ob_start(function ($chunk) {
                return preg_replace('~(<script\b)([^>]*>\s*(/\*.*?\*/)?\s*var\s+SlimStatParams\s*=)~', '$1 data-phast-no-defer$2', $chunk);
            }, 8192);
        });
    }
}
