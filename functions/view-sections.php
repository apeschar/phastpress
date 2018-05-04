<?php

$phastConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->toArray();
$urlWithPhast    = add_query_arg('phast', 'phast',  site_url());
$urlWithoutPhast = add_query_arg('phast', '-phast', site_url());

return [

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
                'name' => __('Remove query string from processed resources', 'phastpress'),
                'description' =>
                    implode('<br>', [
                        __('Make sure that processed resources don\'t have query strings, for a higher score in GTmetrix.'),
                        __('<i>On</i>: Use the path for requests for processed resources. This requires a server that supports "PATH_INFO".'),
                        __('<i>Off</i>: Use the GET parameters for requests for processed resources.')
                    ]),
                'options' => phastpress_render_bool_options('pathinfo-query-format')
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
