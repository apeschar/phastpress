<?php
namespace Kibo\PhastPlugins\PhastPress\Compat;

class Ajax {
    const ACTIONS = [
        'yith_load_product_quick_view',
    ];

    public function setup() {
        if (basename($_SERVER['SCRIPT_FILENAME']) !== 'admin-ajax.php') {
            return;
        }

        if (!isset($_REQUEST['action']) || !in_array($_REQUEST['action'], self::ACTIONS, true)) {
            return;
        }

        add_action('admin_init', function () {
            phastpress_get_plugin_sdk()->getPhastAPI()->deployOutputBufferForSnippets();
        });
    }
}
