<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class Ajax {
    const ACTIONS = [
        'yith_load_product_quick_view',
        'flatsome_quickview',
    ];

    public function setup() {
        if ((
            defined('DOING_AJAX')
            && DOING_AJAX
            && defined('WP_ADMIN')
            && WP_ADMIN
            && isset($_REQUEST['action'])
            && in_array($_REQUEST['action'], self::ACTIONS, true)
        ) || (
            isset($_GET['wc-ajax'])
            && in_array($_GET['wc-ajax'], self::ACTIONS, true)
            && class_exists(\WC_AJAX::class)
        )) {
            add_action('init', function () {
                phastpress_get_plugin_sdk()->getPhastAPI()->deployOutputBufferForSnippets();
            });

            return true;
        }

        return false;
    }
}
