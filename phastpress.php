<?php
/*
Plugin Name: PhastPress
Plugin URI: https://phast.io/
Description: PhastPress is an automated page optimisation plugin for WordPress.
Version: 0.1
Author: Kibo IT
Author URI: https://kiboit.com
License: Proprietary
*/


define('PHASTPRESS_SETTINGS_OPTION', 'phastpress-settings');
define('PHASTPRESS_NONCE_NAME', 'phastpress-nonce');


add_action('plugins_loaded', function () {
    // we have to deploy on plugins_loaded action so we get the wp_get_current_user() to be defined
    if (is_admin()) {
        return;
    }
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/functions.php';

    \Kibo\Phast\PhastDocumentFilters::deploy(phastpress_get_phast_user_config());

    $plugin_config = phastpress_get_config();
    if ($plugin_config['footer-link']) {
        add_action('wp_head', function () {
            echo "<style>
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
                }
            </style>";
        }, 0, 2);
        add_action('wp_footer', function () {
            echo '<div class="phast-footer">'
               . '<a href="https://phast.io/" target="_blank">'
               . __('Optimized by PhastPress', 'phastpress') . '</a></div>';
        });
    }
});

add_action('admin_menu', function () {
    add_options_page(
        __('PhastPress', 'phastpress'),
        __('PhastPress', 'phastpress'),
        'manage_options',
        'phast-press',
        'phastpress_render_settings'
    );

}, 0);

function phastpress_render_settings() {
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/functions.php';

    wp_enqueue_style('phastpress-styles', plugins_url('admin-style.css', __FILE__), [], '0.1');

    if (isset ($_POST['phastpress-use-defaults'])) {
        phastpress_reset_settings();
    } else if (isset ($_POST['phastpress-settings'])) {
        phastpress_save_settings();
    }

    $sections = [

        'phastpress' => [
            'title' => __('PhastPress', 'phastpress'),
            'settings' => [
                [
                    'name' => __('PhastPress General', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_option('enabled', true)
                                .phastpress_render_option('enabled', 'admin', __('On for admins only', 'phastpress'))
                                .phastpress_render_option('enabled', false)
                ],
                [
                    'name' => __('Let the world know about PhastPress', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('footer-link')
                ]
            ],
            'warnings' => [],
            'errors' => []
        ],

        'images' => [
            'title' => __('Images', 'phastpress'),
            'settings' => [
                [
                    'name' => __('Optimize images in tags', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('img-optimization-tags')
                ],
                [
                    'name' => __('Optimize images in CSS', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('img-optimization-css')
                ]
            ],
            'warnings' => [],
            'errors' => []
        ],

        'documents' => [
            'title' => __('CSS &amp; JS', 'phastpress'),
            'settings' => [
                [
                    'name' => __('Optimize CSS', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('css-optimization')
                ],
                [
                    'name' => __('Move scripts to end of body', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('scripts-rearrangement')
                ],
                [
                    'name' => __('Load scripts asynchronously', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('scripts-defer')
                ],
                [
                    'name' => __('Cache external scripts', 'phastpress'),
                    'description' => '',
                    'options' => phastpress_render_bool_options('scripts-proxy')
                ]
            ],
            'warnings' => [],
            'errors' => []
        ]
    ];

    if (!phastpress_get_cache_root()) {
        $sections['phastpress']['errors'][] = sprintf(
            __(
                'PhastPress can not write to any cache directory! Please, make one of the following directories writable: %s',
                'phastpress'
            ),
            join(', ', phastpress_get_cache_root_candidates())
        );
    }
    if (!phastpress_get_security_token()) {
        $sections['phastpress']['errors'][] = sprintf(
            __('PhastPress failed to create a security token in any of the following directories: %s', 'phastpress'),
            join(', ', phastpress_get_cache_root_candidates())
        );
    }

    $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
    foreach ($diagnostics->run(phastpress_get_phast_user_config()) as $status) {
        if (!$status->isAvailable()) {
            $package = $status->getPackage();
            $type = $package->getType();
            $name = substr($package->getNamespace(), strrpos($package->getNamespace(), '\\') + 1);
            if ($type == 'Cache') {
                $sections['phastpress']['errors'][] = $status->getReason();
            } else if (in_array($name, ['Resizer', 'Compression'])) {
                $sections['images']['errors'][] = $status->getReason();
            } else if ($type == 'ImageFilter') {
                $sections['images']['warnings'][] = $status->getReason();
            }
        }
    }

    include __DIR__ . '/templates/main.php';
}

function phastpress_render_option($setting, $value, $label = null) {
    static $config;
    if (!isset ($config)) {
        $config = phastpress_get_config();
    }
    $checked = $config[$setting] === $value ? 'checked' : '';
    if ($value === true) {
        $option_value = 'on';
    } else if ($value === false) {
        $option_value = 'off';
    } else {
        $option_value = $value;
    }
    if (is_null($label)) {
        $label = $value ? __('On', 'phastpress') : __('Off', 'phastpress');
    }
    $option = "<input type=\"radio\" name=\"phastpress-$setting\" value=\"$option_value\" $checked>";
    return "<label>$option\n$label</label>";
}

function phastpress_render_bool_options($setting) {
    return phastpress_render_option($setting, true) . phastpress_render_option($setting, false);
}

function phastpress_save_settings() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    $keys = array_keys(phastpress_get_default_config());
    $settings = [];
    foreach ($keys as $key) {
        $post_key = "phastpress-$key";
        if ($_POST[$post_key] == 'on') {
            $settings[$key] = true;
        } else if ($_POST[$post_key] == 'off') {
            $settings[$key] = false;
        } else {
            $settings[$key] = $_POST[$post_key];
        }
    }
    update_option(PHASTPRESS_SETTINGS_OPTION, $settings);
}

function phastpress_reset_settings() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    delete_option(PHASTPRESS_SETTINGS_OPTION);
}
