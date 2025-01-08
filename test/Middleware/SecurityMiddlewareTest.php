<?php

namespace Lexide\LazyBoy\Test\Middleware;

use Lexide\LazyBoy\Middleware\SecurityMiddleware;
use Lexide\LazyBoy\Response\ResponseFactory;
use Lexide\LazyBoy\Security\AuthoriserInterface;
use Lexide\LazyBoy\Security\AuthoriserResponse;
use Lexide\LazyBoy\Security\ConfigContainer;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\Route;

class SecurityMiddlewareTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected ServerRequestInterface|MockInterface $request;
    protected RequestHandlerInterface|MockInterface $requestHandler;
    protected Route|MockInterface $route;
    protected ResponseFactory|MockInterface $responseFactory;
    protected ResponseInterface|MockInterface $response;
    protected ConfigContainer|MockInterface $securityContainer;
    protected AuthoriserInterface|MockInterface $authoriser;
    protected AuthoriserResponse|MockInterface $authoriserResponse;

    public function setUp(): void
    {
        $this->request = \Mockery::mock(ServerRequestInterface::class);
        $this->requestHandler = \Mockery::mock(RequestHandlerInterface::class);
        $this->route = \Mockery::mock(Route::class);
        $this->route->shouldReceive("getName")->andReturn("foo");
        $this->responseFactory = \Mockery::mock(ResponseFactory::class);
        $this->response = \Mockery::mock(ResponseInterface::class);
        $this->securityContainer = \Mockery::mock(ConfigContainer::class);
        $this->authoriser = \Mockery::mock(AuthoriserInterface::class);
        $this->authoriserResponse = \Mockery::mock(AuthoriserResponse::class);
    }

    public function testNoRoute()
    {
        $this->request->shouldReceive("getAttribute")->once()->andReturnNull();

        $this->responseFactory->shouldReceive("createNotFound")->once()->andReturn($this->response);

        $this->authoriser->shouldNotReceive("checkAuthorisation");

        $middleware = new SecurityMiddleware($this->responseFactory, $this->securityContainer, $this->authoriser);
        $middleware->process($this->request, $this->requestHandler);
    }

    public function testNotAuthorised()
    {
        $this->request->shouldReceive("getAttribute")->andReturn($this->route);
        $this->securityContainer->shouldReceive("getSecurityConfigForRoute")->with("foo")->once()->andReturn([]);

        $this->authoriserResponse->shouldReceive("getSuccess")->andReturnFalse();
        $this->authoriserResponse->shouldReceive("getErrorResponseCode")->andReturn(0);
        $this->authoriser->shouldReceive("checkAuthorisation")->once()->andReturn($this->authoriserResponse);
        $this->responseFactory->shouldReceive("createNotFound")->once()->andReturn($this->response);

        $this->requestHandler->shouldNotReceive("handle");

        $middleware = new SecurityMiddleware($this->responseFactory, $this->securityContainer, $this->authoriser);
        $middleware->process($this->request, $this->requestHandler);
    }

    public function testNotAuthorisedWithResponseCode()
    {
        $code = 401;
        $message = "foo";

        $this->request->shouldReceive("getAttribute")->andReturn($this->route);
        $this->securityContainer->shouldReceive("getSecurityConfigForRoute")->with("foo")->once()->andReturn([]);

        $this->authoriserResponse->shouldReceive("getSuccess")->andReturnFalse();
        $this->authoriserResponse->shouldReceive("getErrorResponseCode")->andReturn($code);
        $this->authoriserResponse->shouldReceive("getErrorMessage")->andReturn($message);
        $this->authoriser->shouldReceive("checkAuthorisation")->once()->andReturn($this->authoriserResponse);

        $this->responseFactory->shouldReceive("createError")->with($message, $code)->once()->andReturn($this->response);

        $this->requestHandler->shouldNotReceive("handle");

        $middleware = new SecurityMiddleware($this->responseFactory, $this->securityContainer, $this->authoriser);
        $middleware->process($this->request, $this->requestHandler);
    }

    public function testAuthorised()
    {
        $this->request->shouldReceive("getAttribute")->andReturn($this->route);
        $this->securityContainer->shouldReceive("getSecurityConfigForRoute")->with("foo")->once()->andReturn([]);

        $this->authoriserResponse->shouldReceive("getSuccess")->andReturnTrue();
        $this->authoriser->shouldReceive("checkAuthorisation")->once()->andReturn($this->authoriserResponse);
        $this->responseFactory->shouldNotReceive("createNotFound");

        $this->requestHandler->shouldReceive("handle")->with($this->request)->once()->andReturn($this->response);

        $middleware = new SecurityMiddleware($this->responseFactory, $this->securityContainer, $this->authoriser);
        $middleware->process($this->request, $this->requestHandler);
    }
}
