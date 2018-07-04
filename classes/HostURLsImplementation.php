<?php

namespace Kibo\PhastPlugins\PhastPress;


use Kibo\Phast\ValueObjects\URL;
use Kibo\PhastPlugins\SDK\HostURLs;

class HostURLsImplementation implements HostURLs {

    public function getServicesURL() {
        return URL::fromString(plugins_url('phast.php', PHASTPRESS_PLUGIN_FILE));
    }

    public function getSiteURL() {
        return URL::fromString(site_url());
    }

    public function getSettingsURL() {
        return URL::fromString(admin_url('options-general.php?page=phast-press'));
    }

    public function getAJAXEndPoint() {
        return URL::fromString('admin-ajax.php?action=phastpress_ajax_dispatch');
    }


    public function getTestImageURL() {
        return URL::fromString(
            plugins_url(
                'sdk/vendor/kiboit/phast/src/Filters/Image/CommonDiagnostics/kibo-logo.png',
                PHASTPRESS_PLUGIN_FILE
            )
        );
    }

}
