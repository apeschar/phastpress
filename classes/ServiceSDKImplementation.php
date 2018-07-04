<?php


namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Common\ServiceHostTrait;
use Kibo\PhastPlugins\SDK\ServiceHost;

class ServiceSDKImplementation implements ServiceHost {
    use ServiceHostTrait;

    public function getCacheRootCandidatesProvider() {
        return new CacheRootCandidatesProviderImplementation();
    }

}
