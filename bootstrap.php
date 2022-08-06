<?php

use Kibo\PhastPlugins\PhastPress\CDN;
use Kibo\PhastPlugins\PhastPress\Compat;

if (!defined('PHASTPRESS_VERSION')) {
    exit;
}

try {
    new PDO('sqlite::memory:');
} catch (\Throwable $e) {
    add_action('admin_notices', function () use ($e) {
        ?>
        <div class="error notice">
            <p><b>Sorry, PhastPress requires a PHP extension that is not installed.</b></p>
            <p>
                The PDO extension needs to be installed with the SQLite3 database driver available.<br>
                Currently we're getting this error: <em><?= esc_html($e->getMessage()); ?></em>
            </p>
            <p>
                Ask your hosting provider to install the <a href="https://www.php.net/manual/en/ref.pdo-sqlite.php" target="_blank" rel="noopener">PDO_SQLITE extension</a>, or downgrade to <a href="https://downloads.wordpress.org/plugin/phastpress.2.1.zip" target="_blank" rel="noopener">PhastPress 2.1</a>.
            </p>
        </div>
        <?php
    });
    return;
}

require_once __DIR__ . '/autoload.php';

/* BEGIN UPDATER */
if (file_exists(__DIR__ . '/classes/Updater.php')) {
    Kibo\PhastPlugins\PhastPress\Updater::setup();
}
/* END UPDATER */

add_action('plugins_loaded', function () {
    if (!get_option('phastpress_1.43')) {
        phastpress_get_plugin_sdk()
            ->getPluginConfiguration()
            ->update([
                'img-optimization-tags' => false,
                'img-optimization-css' => false,
            ]);
        update_option('phastpress_1.43', gmdate('Y-m-d\TH:i:s\Z'));
    }
});

register_activation_hook(PHASTPRESS_PLUGIN_FILE, function () {
    update_option('phastpress_1.43', gmdate('Y-m-d\TH:i:s\Z'));
});

add_action('wp_ajax_phastpress_ajax_dispatch', function () {
    wp_send_json(
        phastpress_get_plugin_sdk()->getAJAXRequestsDispatcher()->dispatch($_POST)
    );
});

add_action('admin_footer', function () {
    echo phastpress_get_plugin_sdk()->getInstallNotice()->render();
});

add_filter('plugin_action_links_' . plugin_basename(PHASTPRESS_PLUGIN_FILE), function ($links) {
    $link = '<a href="' . admin_url('options-general.php?page=phast-press') . '">'
        . __('Settings', 'phastpress') . '</a>';
    array_unshift($links, $link);
    return $links;
});

add_action('plugins_loaded', function () {
    CDN::installHook();
    Compat\Log::setup();

    if ((new Compat\Ajax())->setup()) {
        return;
    }

    if (($priority = has_filter('init', 'wp_cache_late_loader')) !== false) {
        add_action('init', 'phastpress_deploy', $priority + 1);
        Compat\Log::add(
            'wp-super-cache',
            'deploying PhastPress via init hook to support WP Super Cache late init'
        );
        (new Compat\NextGenGallery())->setup($priority + 1);
    } elseif (class_exists(\LiteSpeed\Core::class)
              && ($priority = (new Compat\LiteSpeedCache())->getHookPriority()) !== null
    ) {
        add_action('after_setup_theme', 'phastpress_deploy', $priority + 1);
        Compat\Log::add(
            'litespeed-cache',
            'deploying PhastPress via after_setup_theme hook to support LiteSpeed Cache'
        );
    } else {
        phastpress_deploy();
    }
});

add_action('admin_print_scripts', function () {
    echo phastpress_get_plugin_sdk()->getAutoConfiguration()->renderScript();
});

add_action('admin_menu', function () {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );
});

add_action('wp_head', function () {
    $style = 'font-family:helvetica,sans-serif';
    phastpress_console_log(
        "%cOptimized with %cPhastPress%c %s\nhttps://wordpress.org/plugins/phastpress/",
        $style,
        $style . ';font-weight:bold',
        $style,
        PHASTPRESS_VERSION
    );
});

function phastpress_console_log(...$args) {
    echo phastpress_script(
        sprintf('console.log(%s)', implode(',', array_map('json_encode', $args))),
        ['data-phast-no-defer' => '']
    );
}

function phastpress_render_settings() {
    echo sprintf(
        '<div class="wrap">
            <h1 class="wp-heading-inline">%s</h1>
            %s
         </div>
        ',
        __('PhastPress', 'phastpress'),
        phastpress_get_plugin_sdk()->getAdminPanel()->render()
    );
}

add_action('ai1wm_exclude_content_from_export', function ($filters) {
    if (!is_array($filters)) {
        return $filters;
    }
    foreach (phastpress_get_plugin_sdk()->getCacheRootManager()->getAllCacheRoots() as $dir) {
        $filters[] = $dir;
    }
    return $filters;
});
