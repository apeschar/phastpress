<?php

function phastpress_get_default_config() {
    return [
        'enabled' => 'admin',
        'footer-link' => false,
        'img-optimization-tags' => true,
        'img-optimization-css' => true,
        'img-optimization-api' => false,
        'css-optimization' => true,
        'scripts-rearrangement' => true,
        'scripts-defer' => true,
        'scripts-proxy' => true,
        'iframe-defer' => true,
    ];
}

function phastpress_get_config() {
    $user = get_option(PHASTPRESS_SETTINGS_OPTION, []);
    $default = phastpress_get_default_config();
    return array_merge($default, $user);
}

function phastpress_get_cache_root_candidates() {
    $key = md5($_SERVER['DOCUMENT_ROOT']) . '.' . posix_geteuid();
    return [
        __DIR__ . '/cache/' . $key,
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


function phastpress_get_phast_user_config() {
    $plugin_config = phastpress_get_config();

    $phast_config = array_merge(
        ['servicesUrl' => plugins_url('phast.php', __FILE__)],
        phastpress_get_service_config()
    );
    if ($plugin_config['enabled'] === true) {
        $phast_config['switches']['phast'] = true;
    } else if ($plugin_config['enabled'] === false) {
        $phast_config['switches']['phast'] = false;
    } else {
        $phast_config['switches']['phast'] = current_user_can('administrator');
    }

    $setting2filters = [
        'img-optimization-tags' => ['ImagesOptimizationService\Tags'],
        'img-optimization-css' => ['ImagesOptimizationService\CSS'],
        'css-optimization' => ['CSSInlining', 'CSSDeferring'],
        'scripts-rearrangement' => ['ScriptsRearrangement'],
        'scripts-defer' => ['ScriptsDeferring'],
        'scripts-proxy' => ['ScriptsProxyService'],
        'iframe-defer' => ['DelayedIFrameLoading']
    ];

    $phast_config['documents']['filters'] = [];
    $phast_filters = &$phast_config['documents']['filters'];
    foreach ($setting2filters as $setting => $filters) {
        foreach ($filters as $filter) {
            $fullFilter = "Kibo\Phast\Filters\HTML\\$filter\Filter";
            $phast_filters[$fullFilter] = ['enabled' => $plugin_config[$setting]];
        }
    }
    return $phast_config;
}
