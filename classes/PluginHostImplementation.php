<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Common\PluginHostTrait;
use Kibo\PhastPlugins\SDK\PluginHost;

class PluginHostImplementation extends ServiceSDKImplementation implements PluginHost {
    use PluginHostTrait;

    public function getPluginName() {
        return 'PhastPress';
    }

    public function getPluginHostName() {
        return 'PhastPress';
    }

    public function getPluginHostVersion() {
        $plugin_info = get_file_data(PHASTPRESS_PLUGIN_FILE, ['Version' => 'Version']);
        return $plugin_info['Version'];
    }

    public function getKeyValueStore() {
        return new KeyValueStoreImplementation();
    }

    public function getHostURLs() {
        return new HostURLsImplementation();
    }

    public function getInstallNoticeRenderer() {
        return new InstallNoticeRendererImplementation();
    }

    public function getNonce() {
        return NonceCheckerImplementation::makeNonce();
    }

    public function getNonceChecker() {
        return new NonceCheckerImplementation();
    }

    public function getPhastUser() {
        return new PhastUserImplementation();
    }

    public function getSecurityTokenRoot() {
        if (defined('AUTH_KEY')) {
            return AUTH_KEY;
        }
        return null;
    }

    public function onPhastConfigurationLoad(array $config) {
        $nonce = apply_filters('phastpress_csp_nonce', null);
        if ($nonce !== null) {
            $config['csp']['nonce'] = $nonce;
        }

        return $config;
    }
}
