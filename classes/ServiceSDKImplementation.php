<?php
namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Common\ServiceHostTrait;
use Kibo\PhastPlugins\SDK\ServiceHost;

class ServiceSDKImplementation implements ServiceHost {

    public function getCacheRootCandidatesProvider() {
        return new CacheRootCandidatesProviderImplementation();
    }

    public function onServiceConfigurationLoad(array $config) {
        if (class_exists(\Requests::class)) {
            $config['httpClient'] = RequestsHTTPClient::class;
        }
        return $config;
    }

}
