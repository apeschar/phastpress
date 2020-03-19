<?php

function phastpress_deploy() {
    // We have to deploy on plugins_loaded action so we get wp_get_current_user() to be defined.
    if (is_admin()) {
        return;
    }

    // This is only defined in the main index.php.
    if (!defined('WP_USE_THEMES') || !WP_USE_THEMES) {
        return;
    }

    // Do not optimize when using editor plug-ins.
    if (// Elementor
        isset($_GET['elementor-preview'])
        // YellowPencil
        || (defined('YP_VERSION') && isset($_GET['yellow_pencil_frame']))
        // Divi Visual Builder
        || (@$_GET['et_fb'] === '1' && @$_GET['PageSpeed'] === 'off')
        // Thrive Architect
        || @$_GET['tve'] === 'true'
    ) {
        return;
    }

    // Don't delay Monsterinsights analytics script.
    add_filter('monsterinsights_tracking_analytics_script_attributes', function ($attrs) {
        if (is_array($attrs)) {
            $attrs['data-phast-no-defer'] = '';
        }
        return $attrs;
    });

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

    $sdk = phastpress_get_plugin_sdk();
    $handler = $sdk->getPhastAPI()->deployOutputBufferForDocument();

    // Allow disabling PhastPress with hook.
    add_filter('template_redirect', function () use ($handler) {
        if (apply_filters('phastpress_disable', false)) {
            $handler->cancel();
        }
    }, 100);

    $plugin_config = $sdk->getPluginConfiguration();
    $display_footer = $plugin_config->shouldDisplayFooter();
    if ($display_footer) {
        add_action('wp_head', 'phastpress_render_footer_css', 0, 2);
        add_action('wp_footer', 'phastpress_render_footer');
    }
}

function phastpress_render_footer_css() {
    echo <<<STYLE
<style>
    .phast-footer a:link,
    .phast-footer a:visited,
    .phast-footer a:hover {
        display: block;
        font-size: 12px;
        text-align: center;
        height: 20px;
        background: black;
        color: white;
        position: relative;
        top: 0;
        z-index: 1000;
    }
</style>
STYLE;
}

function phastpress_render_footer() {
    echo '<div class="phast-footer">'
        . '<a href="https://wordpress.org/plugins/phastpress/" target="_blank">'
        . __('Optimized by PhastPress', 'phastpress') . '</a></div>';
}
