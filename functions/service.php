<?php

function phastpress_get_service_sdk() {
    return new Kibo\PhastPlugins\SDK\ServiceSDK(
        new Kibo\PhastPlugins\PhastPress\ServiceSDKImplementation()
    );
}

function phastpress_get_plugin_sdk() {
    return new Kibo\PhastPlugins\SDK\SDK(
        new Kibo\PhastPlugins\PhastPress\PluginHostImplementation()
    );
}
