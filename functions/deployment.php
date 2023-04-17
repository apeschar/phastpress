<?php

use Kibo\PhastPlugins\PhastPress\Compat;

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
        // Thrive Architect
        || (isset($_GET['tve']) && $_GET['tve'] === 'true')
        // Divi Visual Builder
        || (isset($_GET['PageSpeed']) && $_GET['PageSpeed'] === 'off')
        // Child Theme Configurator
        || (isset($_GET['ModPagespeed']) && $_GET['ModPagespeed'] === 'off')
        // Nimble Page Builder
        || (
            defined('NIMBLE_VERSION') && (
                !empty($_GET['customize_changeset_uuid'])
                || !empty($_POST['customize_changeset_uuid'])
            )
        )
        // Asset CleanUp: Page Speed Booster
        || (
            defined('WPACU_LOAD_ASSETS_REQ_KEY')
            && !empty($_REQUEST[WPACU_LOAD_ASSETS_REQ_KEY])
        )
        // WPBakery
        || (
            defined('WPB_VC_VERSION')
            && isset($_GET['vc_editable'])
            && $_GET['vc_editable'] === 'true'
        )
        // Oxygen
        || (
            defined('CT_VERSION')
            && isset($_GET['ct_builder'])
            && $_GET['ct_builder'] === 'true'
        )
    ) {
        Compat\Log::add(null, 'Disabling PhastPress during visual editing');
        return;
    }

    // Support phast_no_defer script attribute.
    add_filter('script_loader_tag', function ($tag, $handle) {
        if (!wp_scripts()->get_data($handle, 'phast_no_defer')) {
            return $tag;
        }

        return preg_replace('~<script\b~i', '$0 data-phast-no-defer', $tag);
    }, 10, 2);

    add_filter('wp_print_scripts', function () {
        foreach (wp_scripts()->registered as $handle => $item) {
            if (empty($item->extra['phast_no_defer'])
                || empty($item->extra['data'])
            ) {
                continue;
            }
            $item->extra['data'] = "'phast-no-defer';\n" . $item->extra['data'];
        }
    });

    foreach ([
        Compat\MonsterInsights::class,
        Compat\Slimstat::class,
        Compat\GoogleSiteKit::class,
        Compat\GAGoogleAnalytics::class,
        Compat\AdThrive::class,
        Compat\TwentyTwentyOneDarkMode::class,
        Compat\BurstStatistics::class,
    ] as $class) {
        (new $class())->setup();
    }

    $sdk = phastpress_get_plugin_sdk();
    $handler = $sdk->getPhastAPI()->deployOutputBufferForDocument();

    // Allow disabling PhastPress with hook.
    if ($handler) {
        add_action('template_redirect', function () use ($handler) {
            if (apply_filters('phastpress_disable', false)) {
                $handler->cancel();
            }
        }, 100);
    }

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

function phastpress_script(string $script, array $attrs = []): string {
    if (!isset($attrs['nonce'])) {
        $nonce = apply_filters('phastpress_csp_nonce', null);
        if ($nonce !== null) {
            $attrs['nonce'] = (string) $nonce;
        }
    }
    return (string) wp_get_inline_script_tag($script, $attrs);
}
