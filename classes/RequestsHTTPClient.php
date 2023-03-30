<?php
namespace Kibo\PhastPlugins\PhastPress;

use Kibo\Phast\HTTP\Client;
use Kibo\Phast\HTTP\Exceptions\NetworkError;
use Kibo\Phast\HTTP\Response;
use Kibo\Phast\ValueObjects\URL;

class RequestsHTTPClient implements Client {
    public function get(URL $url, array $headers = []) {
        if (!is_callable([\Requests::class, 'get'])) {
            throw new NetworkError('\\Requests::get is not available');
        }
        $response = \Requests::get((string) $url, $headers);
        $response->throw_for_status();
        return $this->makePhastResponse($response);
    }

    public function post(URL $url, $data, array $headers = []) {
        if (!is_callable([\Requests::class, 'post'])) {
            throw new NetworkError('\\Requests::post is not available');
        }
        $options = ['connect_timeout' => 10, 'timeout' => 20];
        $response = \Requests::post((string) $url, $headers, $data, $options);
        $response->throw_for_status();
        return $this->makePhastResponse($response);
    }

    /**
     * @param \Requests_Response|\WpOrg\Requests\Response $requestsResponse
     */
    private function makePhastResponse($requestsResponse) {
        $phastResponse = new Response();
        $phastResponse->setContent($requestsResponse->body);
        foreach ($requestsResponse->headers as $name => $value) {
            $phastResponse->setHeader($name, $value);
        }
        return $phastResponse;
    }
}
