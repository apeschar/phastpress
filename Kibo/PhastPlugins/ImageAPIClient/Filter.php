<?php


namespace Kibo\PhastPlugins\ImageAPIClient;


use Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException;
use Kibo\Phast\Filters\Image\Image;
use Kibo\Phast\Filters\Image\ImageFilter;
use Kibo\Phast\Filters\Image\ImageImplementations\DummyImage;

class Filter implements ImageFilter {

    /**
     * @var array
     */
    private $config;

    /**
     * Filter constructor.
     * @param array $config
     */
    public function __construct(array $config) {
        $this->config = $config;
    }


    public function transformImage(Image $image, array $request) {
        if (!function_exists('curl_init')) {
            throw new ImageProcessingException('cURL extension not installed!');
        }
        $ch = curl_init($this->getRequestURL($request));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->getRequestHeaders($request)
        ]);
        $response = @curl_exec($ch);
        if ($response === false) {
            throw new ImageProcessingException(
                'cURL error: ' . curl_error($ch) . ' (' . curl_errno($ch) . ')'
            );
        }
        $info = curl_getinfo($ch);
        if (!preg_match('/^2/', $info['http_code'])) {
            throw new ImageProcessingException(
                'API responded with HTTP code: ' . $info['http_code']
            );
        }
        $newImage = new DummyImage();
        $newImage->setImageString($response);
        $newImage->setType($info['content_type']);
        return $newImage;
    }

    private function getRequestURL(array $request) {
        $params = [];
        foreach (['src', 'width', 'height'] as $key) {
            if (isset ($request[$key])) {
                $params[$key] = $request[$key];
            }
        }
        $glue = strpos($this->config['api-url'], '?') === false ? '?' : '&';
        return $this->config['api-url'] . $glue . http_build_query($params);
    }

    private function getRequestHeaders(array $request) {
        $headers = ['X-Phast-Image-API-Client: ' . $this->getRequestToken()];
        if (isset ($request['preferredType']) && $request['preferredType'] == Image::TYPE_WEBP) {
            $headers[] = 'Accept: image/webp';
        }
        return $headers;
    }

    private function getRequestToken() {
        $token_parts = [];
        foreach (['host-name', 'request-uri', 'admin-email', 'plugin-version'] as $key) {
            $token_parts[$key] = $this->config[$key];
        }
        return json_encode($token_parts);
    }


}
