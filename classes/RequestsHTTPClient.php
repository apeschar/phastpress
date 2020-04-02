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

    private function makePhastResponse(\Requests_Response $requestsResponse) {
        $phastResponse = new Response();
        $phastResponse->setContent($requestsResponse->body);
        foreach ($requestsResponse->headers as $name => $value) {
            $phastResponse->setHeader($name, $value);
        }
        return $phastResponse;
    }

    public static function loadWordPressRequests() {
        if (class_exists(\Requests::class)) {
            return;
        }
        $dir = $_SERVER['SCRIPT_FILENAME'];
        do {
            $dir = dirname($dir);
            $classFile = "$dir/wp-includes/class-requests.php";
            $caFile = "$dir/wp-includes/certificates/ca-bundle.crt";
            if (file_exists($classFile) && file_exists($caFile)) {
                @include_once $classFile;
                if (class_exists(\Requests::class)) {
                    \Requests::register_autoloader();
                    \Requests::set_certificate_path($caFile);
                    return;
                }
            }
        } while ($dir != dirname($dir));
    }
}
