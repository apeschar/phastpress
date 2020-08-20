<?php
namespace Kibo\PhastPlugins\PhastPress;

use Kibo\Phast\ValueObjects\URL;

class CDN {
    public static function installHook() {
        add_filter('phastpress_cdn_url', [static::class, 'getCdnUrl']);
    }

    public static function getCdnUrl($url) {
        if (($options = self::getBunnyCdnOptions())
            && strlen(trim($options['cdn_domain_name']))
        ) {
            return URL::fromString($url)->rewrite(
                URL::fromString($options['site_url']),
                URL::fromString((is_ssl() ? 'https://' : 'http://') . $options['cdn_domain_name'])
            );
        }

        return $url;
    }

    public static function getBunnyCdnOptions() {
        try {
            $class = new \ReflectionClass('BunnyCDN');
            $method = $class->getMethod('getOptions');
            return $method->invoke(null);
        } catch (\Exception $e) {
        } catch (\Throwable $e) {
        }
    }
}
