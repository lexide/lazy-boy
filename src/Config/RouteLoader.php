<?php

namespace Lexide\LazyBoy\Config;

use Lexide\LazyBoy\Exception\RouteException;
use Lexide\LazyBoy\Security\ConfigContainer;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class RouteLoader
{
    const DEFAULT_ALLOWED_METHODS = [
        "GET",
        "POST",
        "PUT",
        "PATCH",
        "DELETE"
    ];

    protected array $controllers;
    protected ConfigContainer $securityContainer;
    protected array $routes;
    protected array $allowedMethods;

    /**
     * @param array $controllers
     * @param ConfigContainer $securityContainer
     * @param array $routes
     * @param ?array $allowedMethods
     */
    public function __construct(
        array $controllers,
        ConfigContainer $securityContainer,
        array $routes,
        ?array $allowedMethods = null
    ) {
        $this->controllers = $controllers;
        $this->securityContainer = $securityContainer;
        $this->routes = $routes;
        $this->setAllowedMethods($allowedMethods ?? self::DEFAULT_ALLOWED_METHODS);
    }

    /**
     * @param array $allowedMethods
     */
    protected function setAllowedMethods(array $allowedMethods): void
    {
        $this->allowedMethods = array_flip(
            array_map(fn($method) => strtoupper($method), $allowedMethods)
        );
    }

    /**
     * @param App $app
     * @throws RouteException
     */
    public function setRoutes(App $app): void
    {
        $this->applyRoutes($app, $this->routes);
    }

    /**
     * @param RouteCollectorProxy $app
     * @param array $config
     * @param array $securityConfig
     * @param string $urlPrefix
     * @throws RouteException
     */
    protected function applyRoutes(RouteCollectorProxy $app, array $config, array $securityConfig = [], string $urlPrefix = ""): void
    {
        foreach ($config["routes"] ?? [] as $name => $route) {
            if (empty($route["url"])) {
                if (empty($urlPrefix)) {
                    throw new RouteException("Route config for '$name' does not contain a URL");
                }
                $route["url"] = "";
            }

            $method = strtoupper($route["method"] ?? "" ?: "GET");
            if (!isset($this->allowedMethods[$method])) {
                throw new RouteException("The method '$method' for route '$name' is not allowed");
            }

            if (empty($route["action"])) {
                throw new RouteException("Route config for '$name' does not contain an action");
            }
            $action = $route["action"];

            if (empty($action["controller"]) || empty($action["method"])) {
                throw new RouteException("Route action for '$name' does not contain both a controller name and method");
            }
            if (empty($this->controllers[$action["controller"]])) {
                throw new RouteException("The controller '{$action["controller"]}' for route '$name' is not registered");
            }
            if (!method_exists($this->controllers[$action["controller"]], $action["method"])) {
                throw new RouteException("The method '{$action["method"]}' does not exist on controller '{$action["controller"]}' for route '$name'");
            }

            $app->map(
                [$method],
                $route["url"],
                $this->createRouteClosure($action["controller"], $action["method"])
            )->setName($name);

            $security = array_replace($securityConfig, $route["security"] ?? []);
            $security["public"] ??= $route["public"] ?? true;

            $this->securityContainer->setSecurityConfigForRoute($name, $security);
        }

        foreach ($config["groups"] ?? [] as $name => $group) {
            if ((
                    empty($group["routes"]) ||
                    !is_array($group["routes"])
                ) && (
                    empty($group["groups"]) ||
                    !is_array($group["groups"])
                )) {
                throw new RouteException("Group for '$name' does not contain any route config");
            }

            $groupSecurity = array_replace($securityConfig, $group["security"] ?? []);
            $groupUrl = $group["url"] ?? "";
            $newPrefix = $urlPrefix . $groupUrl;
            $app->group($groupUrl, function(RouteCollectorProxy $app) use ($group, $groupSecurity, $newPrefix) {
                $this->applyRoutes($app, $group, $groupSecurity, $newPrefix);
            });
        }
    }

    /**
     * @param string $controllerName
     * @param string $method
     * @return \Closure
     */
    protected function createRouteClosure(string $controllerName, string $method): \Closure
    {
        return function(ServerRequestInterface $request, ResponseInterface $response, array $args) use ($controllerName, $method) {
            $controller = $this->controllers[$controllerName];
            $reflection = new \ReflectionMethod($controller, $method);
            $arguments = [];
            foreach ($reflection->getParameters() as $param) {
                $typeName = $param->getType()?->getName();
                switch (true) {
                    case $param->name == "request":
                    case $typeName == RequestInterface::class;
                    case $typeName == ServerRequestInterface::class;
                        $arguments[] = $request;
                        break;
                    case $param->name == "response";
                    case $typeName == ResponseInterface::class;
                        $arguments[] = $response;
                        break;
                    case array_key_exists($param->name, $args):
                        $arguments[] = $args[$param->name];
                        break;
                    default:
                        if (!$param->isOptional()) {
                            throw new RouteException("The argument '{$param->getName()}' is required for the '$controllerName::$method' but it was not supplied");
                        }
                }
            }
            return call_user_func_array([$controller, $method], $arguments);
        };
    }

}