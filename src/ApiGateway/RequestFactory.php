<?php

namespace Lexide\LazyBoy\ApiGateway;

use GuzzleHttp\Psr7\ServerRequest;
use Lexide\LazyBoy\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;

class RequestFactory
{

    /**
     * @param array $event
     * @return ServerRequestInterface
     * @throws RequestException
     */
    public function createFromEvent(array $event): ServerRequestInterface
    {
        $method = $event["httpMethod"] ?? null;
        $path = $event["path"] ?? null;

        if (empty($method) || empty($path)) {
            throw new RequestException("Event does not have a URI and HTTP method");
        }

        $queryParams = [];
        foreach ($event["multiValueQueryStringParameters"] ?? [] as $param => $values) {
            if (str_ends_with($param, "[]")) {
                $param = substr($param, 0, -2);
            }
            if (count($values) == 1) {
                $values = array_pop($values);
            }
            $queryParams[$param] = $values;
        }

        $headers = $event["headers"] ?? [];
        foreach ($headers as $header => $value) {
            $headers[$header] = explode(",", $value);
        }

        $body = $event["body"] ?? null;

        $request = new ServerRequest($method, $path, $headers, $body);
        return $request->withQueryParams($queryParams);
    }

}