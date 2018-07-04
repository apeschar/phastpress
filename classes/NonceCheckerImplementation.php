<?php

namespace Kibo\PhastPlugins\PhastPress;


use Kibo\PhastPlugins\SDK\AdminPanel\Nonce;
use Kibo\PhastPlugins\SDK\Security\NonceChecker;

class NonceCheckerImplementation implements NonceChecker {

    const NONCE_NAME = 'phastpress-nonce';

    public function checkNonce(array $data) {
        return check_admin_referer(self::NONCE_NAME);
    }

    public static function makeNonce() {
        return Nonce::make('_wpnonce', wp_create_nonce(self::NONCE_NAME));
    }

}
