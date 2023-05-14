<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\AdminPanel\InstallNoticeRenderer;

class InstallNoticeRendererImplementation implements InstallNoticeRenderer {
    public function render($notice, $onCloseJSFunction) {
        return phastpress_script(sprintf(
            '
                (function () {
                    var notice = document.createElement("div");
                    notice.className = "notice notice-success is-dismissible";
                    notice.addEventListener("click", %s);
                    notice.innerHTML = %s;

                    var hr =
                        document.querySelector(".wp-header-end") ||
                        document.querySelector("#wpbody-content > .wrap > h1");

                    hr.parentNode.insertBefore(notice, hr.nextSibling);
                })();
            ',
            $onCloseJSFunction,
            json_encode("<p>{$notice}</p>")
        ));
    }
}
