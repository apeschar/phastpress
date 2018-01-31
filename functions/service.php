<?php

function phastpress_get_cache_root_candidates() {
    $key = md5($_SERVER['DOCUMENT_ROOT']) . '.' . posix_geteuid();
    return [
        __DIR__ . '/../cache/' . $key,
        sys_get_temp_dir() . '/phastpress.' . $key
    ];
}

function phastpress_get_cache_root() {
    foreach (phastpress_get_cache_root_candidates() as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
        if (@mkdir($dir, 0777, true)) {
            return $dir;
        }
    }
    return false;
}

function phastpress_get_security_token_filename($dir) {
    return "$dir/security-token.php";
}

function phastpress_generate_security_token($file) {
    $token = \Kibo\Phast\Security\ServiceSignature::generateToken();
    $content = '<?php \'' . addcslashes($token, '\\\'') . '\';';
    $result = @file_put_contents($file, $content);
    return $result;
}


function phastpress_get_security_token() {
    $dir = phastpress_get_cache_root();
    if (!$dir) {
        return false;
    }
    $token_file = phastpress_get_security_token_filename($dir);
    if (!file_exists($token_file)) {
        if (!phastpress_generate_security_token($token_file)) {
            return false;
        }
    }
    $content = @file_get_contents($token_file);
    if (!$content) {
        return false;
    }
    $matches = [];
    if (!preg_match('/^<\?php \'(.*?)\';$/', $content, $matches)) {
        return false;
    }
    return stripcslashes($matches[1]);
}

function phastpress_get_service_config() {
    return [
        'cache' => ['cacheRoot' => phastpress_get_cache_root()],
        'securityToken' => phastpress_get_security_token()
    ];
}

