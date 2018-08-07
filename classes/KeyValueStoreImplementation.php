<?php

namespace Kibo\PhastPlugins\PhastPress;

use Kibo\PhastPlugins\SDK\Configuration\KeyValueStore;

class KeyValueStoreImplementation implements KeyValueStore {

    public function get($key) {
        if (($value = get_option($this->prefixKey($key))) !== false) {
            return $value;
        }
        if (($value = get_option($this->prefixLegacyKey($key))) !== false) {
            return json_encode($value);
        }
    }

    public function set($key, $value) {
        update_option($this->prefixKey($key), $value);
        delete_option($this->prefixLegacyKey($key));
    }

    private function prefixKey($key) {
        return 'phastpress2-' . $key;
    }

    private function prefixLegacyKey($key) {
        return 'phastpress-' . $key;
    }

}
