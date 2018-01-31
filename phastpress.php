<?php
/*
Plugin Name: PhastPress
Description: PhastPress automatically optimizes your site for the best possible Google PageSpeed Insights score.
Version: $VERSION$
Author: Kibo IT
Author URI: https://kiboit.com
License: AGPLv3
*/


define('PHASTPRESS_SETTINGS_OPTION', 'phastpress-settings');
define('PHASTPRESS_NONCE_NAME', 'phastpress-nonce');
define('PHASTPRESS_ACTIVATION_FLAG', 'phastpress-activated');

register_activation_hook(__FILE__, function () {
    update_option(PHASTPRESS_ACTIVATION_FLAG, true);
});

add_action('admin_notices', function () {
    $display_message = get_option(PHASTPRESS_ACTIVATION_FLAG, false);
    if (!$display_message) {
        return;
    }

    require_once __DIR__ . '/functions.php';
    $message = __(
        'Thank you for using <b>PhastPress</b>. Optimization is <b>%s</b>. Go to <b>%s</b> to set up <b>PhastPress</b>!',
        'phastpress'
    );
    $settings_link = phastpress_get_settings_link();
    $config = phastpress_get_config();
    if ($config['enabled'] == 'admin') {
        $status = __('on for administrators', 'phastpress');
    } else if ($config['enabled']) {
        $status = __('on', 'phastpress');
    } else {
        $status = __('off', 'phastpress');
    }

    echo '
        <script>
            jQuery(document).ready(function ($) {
                $("#phastpress-activated-notice").on("click", " .notice-dismiss", function() {
                    $.get(ajaxurl + "?action=phastpress_dismiss_notice")
                })
            });
        </script>';
    echo '<div class="notice notice-success is-dismissible" id="phastpress-activated-notice">';
    echo '<p>' . sprintf($message, $status, $settings_link) . '</p>';
    echo '</div>';
});

add_action('wp_ajax_phastpress_dismiss_notice', function () {
    update_option(PHASTPRESS_ACTIVATION_FLAG, false);
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    admin_url();
    array_unshift($links, phastpress_get_settings_link());
    return $links;
});

function phastpress_get_settings_link() {
    return '<a href="' . admin_url('options-general.php?page=phast-press') . '">'
           . __('Settings', 'phastpress') . '</a>';
}

add_action('plugins_loaded', function () {
    // we have to deploy on plugins_loaded action so we get the wp_get_current_user() to be defined
    if (is_admin()) {
        return;
    }
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/functions.php';

    \Kibo\Phast\PhastDocumentFilters::deploy(phastpress_get_phast_user_config());

    $plugin_config = phastpress_get_config();

    $display_footer = $plugin_config['footer-link']
                      && (
                          $plugin_config['enabled'] === true
                          || ($plugin_config['enabled'] == 'admin' && current_user_can('administrator'))
                      );

    if ($display_footer) {
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
                    z-index: 1000;
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

    $phastConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->toArray();

    $urlWithPhast    = add_query_arg('phast', 'phast',  site_url());
    $urlWithoutPhast = add_query_arg('phast', '-phast', site_url());

    $sections = [

        'phastpress' => [
            'title' => __('PhastPress', 'phastpress'),
            'settings' => [
                [
                    'name' => __('PhastPress optimizations', 'phastpress'),
                    'description' =>
                        __(
                            '<i>On</i>: Enable PhastPress optimizations for all users<br>'
                            . '<i>On for admins only</i>: Enable PhastPress optimizations only for logged-in users '
                            . 'with the "Administrator" privilege. '
                            . 'Use this to test your site before enabling PhastPress for all users.',
                            'phastpress'
                        ) . '<br>' .
                        sprintf(
                            __('<b>Tip:</b> Test your site <a href="%s" target="_blank">without PhastPress</a> and ' .
                               '<a href="%s" target="_blank">with PhastPress</a> in Google PageSpeed Insights.'),
                            esc_attr('https://developers.google.com/speed/pagespeed/insights/?url=' . rawurlencode($urlWithoutPhast)),
                            esc_attr('https://developers.google.com/speed/pagespeed/insights/?url=' . rawurlencode($urlWithPhast))
                        )
                    ,
                    'options' => phastpress_render_option('enabled', true)
                                .phastpress_render_option('enabled', 'admin', __('On for admins only', 'phastpress'))
                                .phastpress_render_option('enabled', false)
                ],
                [
                    'name' => __('Let the world know about PhastPress', 'phastpress'),
                    'description' => __(
                        'Add a "Optimized by PhastPress" notice to the footer of your site and help spread the word.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('footer-link')
                ]
            ],
            'errors' => []
        ],

        'images' => [
            'title' => __('Images', 'phastpress'),
            'settings' => [
                [
                    'name' => __('Optimize images in tags', 'phastpress'),
                    'description' => sprintf(
                        __(
                            'Compress images with optimal settings.<br>Resize images to fit %sx%s pixels, or ' .
                            'to the appropriate size for <code>&lt;img&gt;</code> tags with <code>width</code> or <code>height</code>.<br>' .
                            'Reload changed images while still leveraging browser caching.',
                            'phastpress'
                        ),
                        $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxWidth'],
                        $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxHeight']
                    ),
                    'options' => phastpress_render_bool_options('img-optimization-tags')
                ],
                [
                    'name' => __('Optimize images in CSS', 'phastpress'),
                    'description' => sprintf(
                        __(
                            'Compress images in stylesheets with optimal settings and resizes them to fit %sx%s pixels.<br>' .
                            'Reload changed images while still leveraging browser caching.',
                            'phastpress'
                        ),
                        $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxWidth'],
                        $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxHeight']
                    ),
                    'options' => phastpress_render_bool_options('img-optimization-css')
                ],
                [
                    'name' => __('Use the Phast Image Optimization API', 'phastpress'),
                    'description' => sprintf(
                        __(
                            'Optimize your images on our servers free of charge.<br>' .
                            'This will give you the best possible results without installing any software ' .
                                'and will reduce the load on your hosting.<br>' .
                            '<i>We will use your email address <a href="mailto: %1$s">%1$s</a> ' .
                                'to keep you up to date about changes to the API.</i>',
                            'phastpress'
                        ),
                        get_bloginfo('admin_email')
                    ),
                    'options' => phastpress_render_bool_options('img-optimization-api')
                ]
            ]
        ],

        'documents' => [
            'title' => __('HTML, CSS &amp; JS', 'phastpress'),
            'settings' => [
                [
                    'name' => __('Optimize CSS', 'phastpress'),
                    'description' => __(
                        'Inline critical styles first and prevent unused styles from blocking the page load.<br>' .
                        'Minify stylesheets and leverage browser caching.<br>' .
                        'Inline Google Fonts CSS to speed up font loading.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('css-optimization')
                ],
                [
                    'name' => __('Move scripts to end of body', 'phastpress'),
                    'description' => __(
                        'Prevent scripts from blocking the page load by loading them after HTML and CSS.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('scripts-rearrangement')
                ],
                [
                    'name' => __('Load scripts asynchronously', 'phastpress'),
                    'description' => __(
                        'Allow the page to finishing loading before all scripts have been executed.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('scripts-defer')
                ],
                [
                    'name' => __('Minify scripts and improve caching', 'phastpress'),
                    'description' => __(
                        'Minify scripts and fix caching for Google Analytics and Hotjar.<br>' .
                        'Reload changed scripts while still leveraging browser caching.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('scripts-proxy')
                ],
                [
                    'name' => __('Defer IFrame loading', 'phastpress'),
                    'description' => __(
                        'Start loading IFrames after the page has finished loading.',
                        'phastpress'
                    ),
                    'options' => phastpress_render_bool_options('iframe-defer')
                ]
            ]
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

    $imageFeatures = [
        'Resizer'     => [
            'name' => __('Resize and compress', 'phastpress'),
        ],
        'WEBPEncoder' => [
            'name' => sprintf(__('Convert to <a href="%s" target="_blank">WebP</a>', 'phastpress'), 'https://developers.google.com/speed/webp/')
        ],
        'PNGQuantCompression' => [
            'name' => sprintf(__('Optimize PNG using <a href="%s" target="_blank">pngquant</a>', 'phastpress'), 'https://pngquant.org/')
        ],
        'JPEGTransEnhancer'   => [
            'name' => sprintf(__('Optimize JPEG using <a href="%s" target="_blank">jpegtran</a>', 'phastpress'), 'https://en.wikipedia.org/wiki/Libjpeg#jpegtran')
        ]
    ];

    $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
    foreach ($diagnostics->run(phastpress_get_phast_user_config()) as $status) {
        if (!$status->isAvailable()) {
            $package = $status->getPackage();
            $type = $package->getType();
            $name = substr($package->getNamespace(), strrpos($package->getNamespace(), '\\') + 1);
            if ($type == 'Cache') {
                $sections['phastpress']['errors'][] = $status->getReason();
            } else if ($type == 'ImageFilter') {
                $name = $name == 'Compression' ? 'Resizer' : $name;
                $imageFeatures[$name]['error'] = $status->getReason();
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
    $disabled = $setting != 'enabled' && $config['enabled'] === false ? 'disabled' : '';
    $option = "<input type=\"radio\" name=\"phastpress-$setting\" value=\"$option_value\" $checked $disabled>";
    return "<label>$option\n$label</label>";
}

function phastpress_render_bool_options($setting) {
    return phastpress_render_option($setting, true) . phastpress_render_option($setting, false);
}

function phastpress_save_settings() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    $keys = array_keys(phastpress_get_default_config());
    $settings = phastpress_get_config();
    foreach ($keys as $key) {
        $post_key = "phastpress-$key";
        if (!isset($_POST[$post_key])) {
            continue;
        }
        if ($_POST[$post_key] == 'on') {
            $settings[$key] = true;
        } else if ($_POST[$post_key] == 'off') {
            $settings[$key] = false;
        } else if ($_POST[$post_key] == 'admin') {
            $settings[$key] = 'admin';
        }
    }
    update_option(PHASTPRESS_SETTINGS_OPTION, $settings);
}

function phastpress_reset_settings() {
    check_admin_referer(PHASTPRESS_NONCE_NAME);
    delete_option(PHASTPRESS_SETTINGS_OPTION);
}
