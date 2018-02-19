<?php


namespace Kibo\PhastPlugins\ImageAPIClient;


use Kibo\Phast\Filters\Image\Exceptions\ImageProcessingException;
use Kibo\Phast\Filters\Image\Image;
use Kibo\Phast\Filters\Image\ImageFilter;
use Kibo\Phast\Filters\Image\ImageImplementations\DummyImage;
use Kibo\Phast\Security\ServiceSignature;
use Kibo\Phast\Services\ServiceRequest;
use Kibo\Phast\ValueObjects\URL;

class Filter implements ImageFilter {

    /**
     * @var array
     */
    private $config;

    /**
     * @var ServiceSignature
     */
    private $signature;

    /**
     * Filter constructor.
     * @param array $config
     * @param ServiceSignature $signature
     */
    public function __construct(array $config, ServiceSignature $signature) {
        $this->config = $config;
        $this->signature = $signature;
        $this->signature->setIdentities('');
    }


    public function transformImage(Image $image, array $request) {
        if (!function_exists('curl_init')) {
            throw new ImageProcessingException('cURL extension not installed!');
        }
        $ch = curl_init($this->getRequestURL($request));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->getRequestHeaders($image, $request),
            CURLOPT_POSTFIELDS => $image->getAsString(),
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 10
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
        foreach (['width', 'height'] as $key) {
            if (isset ($request[$key])) {
                $params[$key] = $request[$key];
            }
        }
        return (new ServiceRequest())
            ->withUrl(URL::fromString($this->config['api-url']))
            ->withParams($params)
            ->sign($this->signature)
            ->serialize();
    }

    private function getRequestHeaders(Image $image, array $request) {
        $headers = [
            'X-Phast-Image-API-Client: ' . $this->getRequestToken(),
            'Content-Type: ' . $image->getType()
        ];
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
