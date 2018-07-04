<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\AdminPanel\InstallNoticeRenderer;

class InstallNoticeRendererImplementation implements InstallNoticeRenderer {

    public function render($notice, $onCloseJSFunction) {
        $template = '
        <script>
            jQuery(document).ready(function ($) {
                $("#phastpress-activated-notice").on("click", " .notice-dismiss", function() {
                    (%s)()
                })
            });
        </script>';
        $template = sprintf($template, $onCloseJSFunction);
        $template .= '<div class="notice notice-success is-dismissible" id="phastpress-activated-notice">';
        $template .= '<p>' . $notice . '</p>';
        $template .= '</div>';
        return $template;
    }

}
