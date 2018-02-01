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

function phastpress_store_in_php_file($filename, $value) {
    $content = '<?php \'' . addcslashes($value, '\\\'') . '\';';
    return @file_put_contents($filename, $content);
}

function phastpress_read_from_php_file($filename) {
    $content = @file_get_contents($filename);
    if (!$content) {
        return false;
    }
    $matches = [];
    if (!preg_match('/^<\?php \'(.*?)\';$/', $content, $matches)) {
        return false;
    }
    return stripcslashes($matches[1]);
}

function phastpress_get_cache_stored_file_path($filename) {
    $dir = phastpress_get_cache_root();
    if (!$dir) {
        return false;
    }
    return "$dir/$filename.php";
}

function phastpress_get_security_token_filename() {
    return phastpress_get_cache_stored_file_path('security-token');
}

function phastpress_generate_security_token($file) {
    $token = \Kibo\Phast\Security\ServiceSignature::generateToken();
    return phastpress_store_in_php_file($file, $token);
}


function phastpress_get_security_token() {
    $token_file = phastpress_get_security_token_filename();
    if (!$token_file) {
        return false;
    }
    if (!file_exists($token_file)) {
        if (!phastpress_generate_security_token($token_file)) {
            return false;
        }
    }
    return phastpress_read_from_php_file($token_file);
}

function phastpress_get_service_config() {
    return [
        'cache' => ['cacheRoot' => phastpress_get_cache_root()],
        'securityToken' => phastpress_get_security_token()
    ];
}

