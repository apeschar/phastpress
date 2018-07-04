<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Configuration\KeyValueStore;

class KeyValueStoreImplementation implements KeyValueStore {

    public function get($key, $default = null) {
        return json_encode(get_option($this->prefixKey($key), $default));
    }

    public function set($key, $value) {
        update_option($this->prefixKey($key), json_decode($value, true));
    }

    private function prefixKey($key) {
        return 'phastpress-' . $key;
    }

}
