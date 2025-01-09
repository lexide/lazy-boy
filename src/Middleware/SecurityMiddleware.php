<?php

namespace Lexide\LazyBoy\Middleware;

use Lexide\LazyBoy\Response\ResponseFactory;
use Lexide\LazyBoy\Security\AuthoriserInterface;
use Lexide\LazyBoy\Security\ConfigContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;

class SecurityMiddleware implements MiddlewareInterface
{

    protected ResponseFactory $responseFactory;
    protected ConfigContainer $securityContainer;
    protected AuthoriserInterface $authoriser;

    /**
     * @param ResponseFactory $responseFactory
     * @param ConfigContainer $securityContainer
     * @param AuthoriserInterface $authoriser
     */
    public function __construct(
        ResponseFactory $responseFactory,
        ConfigContainer $securityContainer,
        AuthoriserInterface $authoriser
    ) {
        $this->responseFactory = $responseFactory;
        $this->securityContainer = $securityContainer;
        $this->authoriser = $authoriser;
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute(RouteContext::ROUTE);

        if (!$route instanceof RouteInterface) {
            return $this->responseFactory->createNotFound();
        }

        $security = $this->securityContainer->getSecurityConfigForRoute($route->getName());

        $authResponse = $this->authoriser->checkAuthorisation($request, $security);

        return match (true) {
            $authResponse->getSuccess() => $handler->handle($request),
            ($authResponse->getErrorResponseCode() == 0) => $this->responseFactory->createNotFound(),
            default => $this->responseFactory->createError(
                $authResponse->getErrorMessage() ?: "Authorisation error",
                $authResponse->getErrorResponseCode()
            )
        };
    }

}