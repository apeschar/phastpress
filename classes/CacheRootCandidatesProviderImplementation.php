<?php

namespace Kibo\PhastPlugins\PhastPress;


use Kibo\PhastPlugins\SDK\Caching\CacheRootCandidatesProvider;

class CacheRootCandidatesProviderImplementation implements CacheRootCandidatesProvider {

    public function getCacheRootCandidates() {
        return [
            WP_CONTENT_DIR . '/cache',
            WP_CONTENT_DIR . '/uploads',
            __DIR__ . '/../cache',
            sys_get_temp_dir()
        ];
    }
}
