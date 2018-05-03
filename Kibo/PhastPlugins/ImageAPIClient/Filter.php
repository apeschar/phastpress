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

    public function getCacheSalt(array $request) {
        $result = 'api-call';
        foreach (['width', 'height', 'preferredType'] as $key) {
            if (isset ($request[$key])) {
                $result .= "-$key-{$request[$key]}";
            }
        }
        return $result;
    }


    public function transformImage(Image $image, array $request) {
        $url = $this->getRequestURL($request);
        $headers = $this->getRequestHeaders($image, $request);
        $data = $image->getAsString();
        $options = ['connect_timeout' => 2, 'timeout' => 10];
        try {
            $response = \Requests::post($url, $headers, $data, $options);
            $response->throw_for_status();
        } catch (\Exception $e) {
            throw new ImageProcessingException(
                'Request exception: ' . get_class($e)
                . ' MSG: ' . $e->getMessage()
                . ' Code: ' . $e->getCode()
            );
        }
        $newImage = new DummyImage();
        $newImage->setImageString($response->body);
        $newImage->setType($response->headers['Content-type']);
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
            ->serialize(ServiceRequest::FORMAT_QUERY);
    }

    private function getRequestHeaders(Image $image, array $request) {
        $headers = [
            'X-Phast-Image-API-Client' => $this->getRequestToken(),
            'Content-Type' => $image->getType()
        ];
        if (isset ($request['preferredType']) && $request['preferredType'] == Image::TYPE_WEBP) {
            $headers['Accept'] = 'image/webp';
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
