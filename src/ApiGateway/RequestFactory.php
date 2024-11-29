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

        $queryString = $event["rawQueryString"] ?? "";

        $uri = $path . (!empty($queryString) ? "?$queryString" : "");
        parse_str($queryString, $queryParams);

        $headers = $event["headers"] ?? [];
        foreach ($headers as $header => $value) {
            $headers[$header] = explode(",", $value);
        }

        $body = $event["body"] ?? null;

        $request = new ServerRequest($method, $uri, $headers, $body);
        return $request->withQueryParams($queryParams);
    }

}