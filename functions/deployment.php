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

    // Allow disabling PhastPress with hook.
    if (apply_filters('phastpress_disable', false)) {
        return;
    }

    $sdk = phastpress_get_plugin_sdk();
    $sdk->getPhastAPI()->deployOutputBufferForDocument();

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
