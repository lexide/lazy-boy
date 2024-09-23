<?php

namespace Lexide\LazyBoy\Middleware;

use Lexide\LazyBoy\Response\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{

    protected ResponseFactory $responseFactory;
    protected array $allowedMethods;
    protected array $allowedOrigins;
    protected array $allowedHeaders;

    /**
     * @param ResponseFactory $responseFactory
     * @param array $allowedMethods
     * @param array $allowedOrigins
     * @param array $allowedHeaders
     */
    public function __construct(
        ResponseFactory $responseFactory,
        array $allowedMethods,
        array $allowedOrigins = [],
        array $allowedHeaders = []
    ) {
        $this->responseFactory = $responseFactory;
        $this->allowedMethods = $allowedMethods;
        $this->allowedOrigins = $allowedOrigins;
        $this->allowedHeaders = $allowedHeaders;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $request->getMethod() == "OPTIONS"
            ? $this->responseFactory->createNoContent()
            : $handler->handle($request);

        $methods = $this->allowedMethods;
        $methods[] = "OPTIONS";
        $methods = implode(", ", array_flip(array_flip($methods))); // fast dedupe

        $origins = implode(", ", $this->allowedOrigins);

        $headers = implode(", ", $this->allowedHeaders);

        return $response
            ->withHeader("Access-Control-Allow-Credentials", "true")
            ->withHeader("Access-Control-Allow-Origin", $origins ?: "*")
            ->withHeader("Access-Control-Allow-Headers", $headers ?: "*")
            ->withHeader("Access-Control-Allow-Methods", $methods)
            ->withHeader("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
            ->withHeader("Pragma", "no-cache");
    }

}