<?php

function phastpress_deploy() {
    // we have to deploy on plugins_loaded action so we get the wp_get_current_user() to be defined
    if (is_admin()) {
        return;
    }

    require_once __DIR__ . '/../vendor/autoload.php';
    \Kibo\Phast\PhastDocumentFilters::deploy(phastpress_get_phast_user_config());

    $plugin_config = phastpress_get_config();
    $display_footer = $plugin_config['footer-link']
        && (
            $plugin_config['enabled'] === true
            || ($plugin_config['enabled'] == 'admin' && current_user_can('administrator'))
        );

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
