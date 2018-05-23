<?php

function phastpress_render_plugin_install_notice() {

    $display_message = get_option(PHASTPRESS_ACTIVATION_NOTIFICATION_FLAG, false);
    if (!$display_message) {
        return;
    }

    $message = __(
        'Thank you for using <b>PhastPress</b>. Optimizations are <b>%s</b>. Go to <b>%s</b> to configure <b>PhastPress</b>.',
        'phastpress'
    );
    $settings_link = phastpress_get_settings_link();
    $config = phastpress_get_config();
    if ($config['enabled'] && $config['admin-only']) {
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

}

function phastpress_get_settings_link() {
    return '<a href="' . admin_url('options-general.php?page=phast-press') . '">'
        . __('Settings', 'phastpress') . '</a>';
}

function phastpress_get_admin_panel_data() {
    $phastConfig = \Kibo\Phast\Environment\Configuration::fromDefaults()->toArray();
    $urlWithPhast    = add_query_arg('phast', 'phast',  site_url());
    $urlWithoutPhast = add_query_arg('phast', '-phast', site_url());
    $pageSpeedToolUrl = 'https://developers.google.com/speed/pagespeed/insights/?url=';

    $errors = [];
    if (!phastpress_has_cache_root()) {
        $errors[] = [
           'type' => 'no-cache-root',
           'params' => phastpress_get_cache_root_candidates()
        ];
    }
    if (!phastpress_has_service_config()) {
        $errors[] = [
            'type' => 'no-service-config',
            'params' => phastpress_get_cache_root_candidates()
        ];
    }

    $warnings = [];
    $api_client_warning = [];
    $phast_config = phastpress_get_phast_user_config();
    $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
    foreach ($diagnostics->run($phast_config) as $status) {
        if ($status->isAvailable()) {
            continue;
        }
        $package = $status->getPackage();
        $type = $package->getType();
        if ($type == 'Cache') {
            $errors[] = [
                'type' => 'cache',
                'params' => [$status->getReason()]
            ];
        } else if ($type == 'ImageFilter') {
            $name = substr($package->getNamespace(), strrpos($package->getNamespace(), '\\') + 1);
            if ($name === 'ImageAPIClient') {
                $api_client_warning[] = 'PhastPress Image API error: ' . $status->getReason();
            } else {
                $warnings[] = $status->getReason();
            }
        }
    }

    $phastpress_config = phastpress_get_config();
    if ($phastpress_config['img-optimization-api']) {
        $warnings = $api_client_warning;
    }

    return [
        'config' => phastpress_get_config(),
        'settingsStrings' => [
            'adminEmail' => get_bloginfo('admin_email'),
            'urlWithPhast' => $pageSpeedToolUrl . rawurlencode($urlWithPhast),
            'urlWithoutPhast' => $pageSpeedToolUrl . rawurlencode($urlWithoutPhast),
            'maxImageWidth'
                => $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxWidth'],
            'maxImageHeight'
                => $phastConfig['images']['filters'][\Kibo\Phast\Filters\Image\Resizer\Filter::class]['defaultMaxHeight']
        ],
        'errors' => $errors,
        'warnings' => $warnings,
        'nonce' => wp_create_nonce(PHASTPRESS_NONCE_NAME),
        'nonceName' => '_wpnonce',
    ];
}
