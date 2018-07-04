<?php

add_action('admin_menu', 'phastpress_register_menu', 0);

function phastpress_register_menu() {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_low_php'
    );
}

function phastpress_render_low_php() {
    echo sprintf(
        '<div class="wrap"><h1 class="wp-heading-inline">%s</h1>%s</div>',
        __('PhastPress', 'phastpress'),
        Phast_Plugins_Bootstrap::renderLowPHPScreen()
    );
}
