<?php
namespace Kibo\PhastPlugins\PhastPress;

use Kibo\Phast\HTTP\Client;
use Kibo\Phast\HTTP\Exceptions\NetworkError;
use Kibo\Phast\HTTP\Response;
use Kibo\Phast\ValueObjects\URL;
use WpOrg\Requests\{Requests, Response as RequestsResponse};

class RequestsHTTPClient implements Client {
    public function get(URL $url, array $headers = []): Response {
        $response = Requests::get((string) $url, $headers);
        $response->throw_for_status();
        return $this->makePhastResponse($response);
    }

    public function post(URL $url, $data, array $headers = []): Response {
        $options = ['connect_timeout' => 10, 'timeout' => 20];
        $response = Requests::post((string) $url, $headers, $data, $options);
        $response->throw_for_status();
        return $this->makePhastResponse($response);
    }

    private function makePhastResponse(RequestsResponse $requestsResponse): Response {
        $phastResponse = new Response();
        $phastResponse->setContent($requestsResponse->body);
        foreach ($requestsResponse->headers as $name => $value) {
            $phastResponse->setHeader($name, $value);
        }
        return $phastResponse;
    }
}
