<?php

function phastpress_render_plugin_install_notice() {

    $display_message = get_option(PHASTPRESS_ACTIVATION_FLAG, false);
    if (!$display_message) {
        return;
    }

    $message = __(
        'Thank you for using <b>PhastPress</b>. Optimizations are <b>%s</b>. Go to <b>%s</b> to configure <b>PhastPress</b>.',
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

}

function phastpress_get_settings_link() {
    return '<a href="' . admin_url('options-general.php?page=phast-press') . '">'
        . __('Settings', 'phastpress') . '</a>';
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

function phastpress_render_settings() {
    require_once __DIR__ . '/../vendor/autoload.php';

    wp_enqueue_style('phastpress-styles');

    if (isset ($_POST['phastpress-use-defaults'])) {
        phastpress_reset_config();
    } else if (isset ($_POST['phastpress-settings'])) {
        phastpress_save_config();
    }

    $sections = require __DIR__ . '/view-sections.php';

    if (!phastpress_get_cache_root()) {
        $sections['phastpress']['errors'][] = sprintf(
            __(
                'PhastPress can not write to any cache directory! Please, make one of the following directories writable: %s',
                'phastpress'
            ),
            join(', ', phastpress_get_cache_root_candidates())
        );
    }
    if (!phastpress_get_service_config()) {
        $sections['phastpress']['errors'][] = sprintf(
            __(
                'PhastPress failed to create a service configuration in any of the following directories: %s',
                'phastpress'
            ),
            join(', ', phastpress_get_cache_root_candidates())
        );
    }

    $phast_config = phastpress_get_phast_user_config();
    $image_features = require __DIR__ . '/view-image-features.php';

    $diagnostics = new \Kibo\Phast\Diagnostics\SystemDiagnostics();
    foreach ($diagnostics->run($phast_config) as $status) {
        if (!$status->isAvailable()) {
            $package = $status->getPackage();
            $type = $package->getType();
            $name = substr($package->getNamespace(), strrpos($package->getNamespace(), '\\') + 1);
            if ($type == 'Cache') {
                $sections['phastpress']['errors'][] = $status->getReason();
            } else if ($type == 'ImageFilter') {
                $name = $name == 'Compression' ? 'Resizer' : $name;
                $image_features[$name]['error'] = $status->getReason();
            }
        }
    }

    $phastpress_config = phastpress_get_config();
    if ($phastpress_config['img-optimization-api']) {
        foreach (array_keys($image_features) as $name) {
            if ($name != 'ImageAPIClient' && isset ($image_features['ImageAPIClient']['error'])) {
                $image_features[$name]['error'] = $image_features['ImageAPIClient']['error'];
            }else if ($name != 'ImageAPIClient' && isset ($image_features[$name]['error'])) {
                unset ($image_features[$name]['error']);
            }
        }
    } else {
        unset ($image_features['ImageAPIClient']);
    }


    include __DIR__ . '/../templates/main.php';
}
