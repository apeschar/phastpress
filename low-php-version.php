<?php

add_action('wp_ajax_phastpress_get_admin_panel_data', function () {
    wp_send_json(array(
        'error' => array(
            'type' => 'low-php',
            'version' => PHP_VERSION
        )
    ));
});