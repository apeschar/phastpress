<?php
namespace Kibo\PhastPlugins\PhastPress;

use Kibo\Phast\HTTP\Request;
use Kibo\PhastPlugins\SDK\ServiceHost;

class ServiceSDKImplementation implements ServiceHost {
    public function getCacheRootCandidatesProvider() {
        return new CacheRootCandidatesProviderImplementation();
    }

    public function onServiceConfigurationLoad(array $config) {
        if (class_exists(\Requests::class)) {
            $config['httpClient'] = RequestsHTTPClient::class;
        }

        if ($map = $this->getRetrieverMap()) {
            $config['retrieverMap'] = $map;
        }

        return $config;
    }

    private function getRetrieverMap() {
        if (!defined('MULTISITE')
            || !MULTISITE
            || !defined('SUBDOMAIN_INSTALL')
            || SUBDOMAIN_INSTALL
        ) {
            return null;
        }

        $request = Request::fromGlobals();

        $map = [
            '/' => $request->getDocumentRoot(),
        ];

        foreach (['wp-content', 'wp-admin', 'wp-includes'] as $dir) {
            $map["/[_0-9a-zA-Z-]+/$dir/"] = "{$request->getDocumentRoot()}/$dir";
        }

        return [
            $request->getHost() => $map,
        ];
    }
}
