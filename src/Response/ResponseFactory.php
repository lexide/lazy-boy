<?php

namespace Lexide\LazyBoy\Response;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ResponseFactory
{
    /**
     * @param mixed $data
     * @param int $statusCode
     * @return ResponseInterface
     */
    public function createJson(mixed $data, int $statusCode = 200): ResponseInterface
    {
        return new Response(
            $statusCode,
            ["Content-Type" => "application/json"],
            json_encode($data)
        );
    }

    /**
     * @return ResponseInterface
     */
    public function createNoContent(): ResponseInterface
    {
        return $this->createResponse(204);
    }

    /**
     * @return ResponseInterface
     */
    public function createNotFound(): ResponseInterface
    {
        return $this->createError("Not found", 404);
    }

    /**
     * @param string $message
     * @param int $code
     * @param ?array $context
     * @return ResponseInterface
     */
    public function createError(string $message, int $code = 400, ?array $context = null): ResponseInterface
    {
        $payload = [
            "error" => $message
        ];
        if (!is_null($context)) {
            $payload["context"] = $context;
        }

        return $this->createJson($payload, $code);
    }

    /**
     * @param int $statusCode
     * @param array $headers
     * @param string|StreamInterface|null $body
     * @return ResponseInterface
     */
    public function createResponse(
        int $statusCode = 200,
        array $headers = [],
        string|StreamInterface|null $body = null
    ): ResponseInterface {
        return new Response($statusCode, $headers, $body);
    }

}