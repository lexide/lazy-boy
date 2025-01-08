<?php

namespace Lexide\LazyBoy\Test\Config;

use Lexide\LazyBoy\Config\RouteLoader;
use Lexide\LazyBoy\Exception\RouteException;
use Lexide\LazyBoy\Security\ConfigContainer;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Interfaces\RouteGroupInterface;
use Slim\Interfaces\RouteInterface;

class RouteLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected ConfigContainer|MockInterface $securityContainer;
    protected App|MockInterface $app;
    protected RouteInterface|MockInterface $route;
    protected RouteGroupInterface|MockInterface $group;
    protected ServerRequestInterface|MockInterface $request;
    protected ResponseInterface|MockInterface $response;
    protected MockController $controller;

    public function setUp(): void
    {
        $this->securityContainer = \Mockery::mock(ConfigContainer::class);
        $this->app = \Mockery::mock(App::class);
        $this->route = \Mockery::mock(RouteInterface::class);
        $this->group = \Mockery::mock(RouteGroupInterface::class);
        $this->request = \Mockery::mock(ServerRequestInterface::class);
        $this->response = \Mockery::mock(ResponseInterface::class);
        $this->controller = new MockController();
    }


    /**
     * @dataProvider routesProvider
     *
     * @param array $routes
     * @param array $expectedRouteMapping
     * @param array $expectedSecurityConfig
     * @param array $expectedGroupMapping
     * @throws RouteException
     */
    public function testSettingRoutes(
        array $routes,
        array $expectedRouteMapping,
        array $expectedSecurityConfig,
        array $expectedGroupMapping = []
    ) {

        $expectedControllerCalls = [];

        foreach ($expectedRouteMapping as $name => ["method" => $method, "url" => $url, "action" => $actionMethod]) {
            $this->app->shouldReceive("map")->with([strtoupper($method)], $url, \Mockery::type("callable"))->once()->andReturnUsing(
                function($foo, $bar, $callable) {
                    $callable($this->request, $this->response, []);
                    return $this->route;
                }
            );
            $this->route->shouldReceive("setName")->with($name)->once();
            $expectedControllerCalls[$actionMethod] = $expectedControllerCalls[$actionMethod] ?? 0;
            ++$expectedControllerCalls[$actionMethod];
        }

        foreach ($expectedGroupMapping as $groupUrl) {
            $this->app->shouldReceive("group")->with($groupUrl, \Mockery::type("callable"))->once()->andReturnUsing(
                function ($foo, $callable) {
                    $callable($this->app);
                    return $this->group;
                }
            );
        }

        foreach ($expectedSecurityConfig as $route => $config) {
            $this->securityContainer->shouldReceive("setSecurityConfigForRoute")->with($route, $config)->once();
        }

        $loader = new RouteLoader(
            ["mock" => $this->controller],
            $this->securityContainer,
            $routes
        );

        $loader->setRoutes($this->app);

        $callCount = $this->controller->getCallCount();
        foreach ($expectedControllerCalls as $method => $count) {
            $this->assertArrayHasKey($method, $callCount);
            $this->assertSame($count, $callCount[$method]);
            unset($callCount[$method]);
        }
        if (!empty($callCount)) {
            $this->fail("More controller calls were made than were expected:\n" . json_encode($callCount));
        }

    }

    /**
     * @dataProvider invalidRoutesProvider
     *
     * @param array $routes
     * @param string $expectedExceptionRegex
     * @param ?array $allowedMethods
     * @throws RouteException
     */
    public function testRouteExceptions(array $routes, string $expectedExceptionRegex, ?array $allowedMethods = null)
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessageMatches($expectedExceptionRegex);

        $loader = new RouteLoader(
            ["mock" => $this->controller],
            $this->securityContainer,
            $routes,
            $allowedMethods
        );

        $loader->setRoutes($this->app);
    }

    /**
     * @dataProvider routeClosureProvider
     *
     * @param array $routes
     * @param string $expectedMethod
     * @throws RouteException
     */
    public function testRouteClosure(string $expectedMethod, array $args = [])
    {

        $routes = [
            "routes" => [
                "test" => [
                    "url" => "foo",
                    "action" => [
                        "controller" => "mock",
                        "method" => $expectedMethod
                    ]
                ]
            ]
        ];

        $this->app->shouldReceive("map")->with(["GET"], "foo", \Mockery::type("callable"))->once()->andReturnUsing(
            function($foo, $bar, $callable) use ($args) {
                $callable($this->request, $this->response, $args);
                return $this->route;
            }
        );
        $this->securityContainer->shouldIgnoreMissing();
        $this->route->shouldIgnoreMissing();

        $loader = new RouteLoader(
            ["mock" => $this->controller],
            $this->securityContainer,
            $routes
        );

        $loader->setRoutes($this->app);

        $calls = $this->controller->getCallCount();
        $this->assertArrayHasKey($expectedMethod, $calls);
        $this->assertSame(1, $calls[$expectedMethod]);
    }

    public function testRequiredControllerArgs()
    {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessageMatches("/argument.*is required/");

        $routes = [
            "routes" => [
                "test" => [
                    "url" => "foo",
                    "action" => [
                        "controller" => "mock",
                        "method" => "addArg"
                    ]
                ]
            ]
        ];

        $this->app->shouldReceive("map")->with(["GET"], "foo", \Mockery::type("callable"))->once()->andReturnUsing(
            function($foo, $bar, $callable) {
                $callable($this->request, $this->response, []);
                return $this->route;
            }
        );
        $this->securityContainer->shouldIgnoreMissing();
        $this->route->shouldIgnoreMissing();

        $loader = new RouteLoader(
            ["mock" => $this->controller],
            $this->securityContainer,
            $routes
        );

        $loader->setRoutes($this->app);
    }

    public function routesProvider(): array
    {

        return [
            "simple route" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo")
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one")
            ],
            "defaulted method" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("", "foo", "mock", "callFoo")
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one")
            ],
            "multiple routes" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo"),
                        "two" => $this->formatRouteConfig("post", "bar", "mock", "callBar"),
                        "three" => $this->formatRouteConfig("put", "baz", "mock", "callBaz")
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "two" => [
                        "method" => "post",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "three" => [
                        "method" => "put",
                        "url" => "baz",
                        "action" => "callBaz"
                    ]
                ],
                array_merge(
                    $this->formatSecurityConfig("one"),
                    $this->formatSecurityConfig("two"),
                    $this->formatSecurityConfig("three")
                )
            ],
            "private route" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo", [], false)
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one", ["public" => false])
            ],
            "custom security" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo", ["foo" => "bar", "user" => 123])
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one", ["foo" => "bar", "user" => 123])
            ],
            "simple group" => [
                [
                    "groups" => [
                        "foo" => [
                            "routes" => [
                                "one" => $this->formatRouteConfig("get", "bar", "mock", "callBar")
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ]
                ],
                $this->formatSecurityConfig("one"),
                [""]
            ],
            "group with url" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "routes" => [
                                "one" => $this->formatRouteConfig("get", "bar", "mock", "callBar")
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ]
                ],
                $this->formatSecurityConfig("one"),
                ["foo"]
            ],
            "group security" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["role" => "admin", "public" => false],
                            "routes" => [
                                "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo")
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one", ["role" => "admin", "public" => false]),
                ["foo"]
            ],
            "route security overrides group security" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["role" => "user", "public" => false],
                            "routes" => [
                                "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo", ["role" => "admin"])
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                $this->formatSecurityConfig("one", ["role" => "user", "public" => false], ["role" => "admin"]),
                ["foo"]
            ],
            "nested groups" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "groups" => [
                                "bar" => [
                                    "url" => "bar",
                                    "routes" => [
                                        "one" => $this->formatRouteConfig("get", "bar", "mock", "callBar")
                                    ]
                                ],
                                "baz" => [
                                    "url" => "baz",
                                    "routes" => [
                                        "two" => $this->formatRouteConfig("get", "baz", "mock", "callBaz"),
                                        "three" => $this->formatRouteConfig("post", "baz", "mock", "callBaz")
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "two" => [
                        "method" => "get",
                        "url" => "baz",
                        "action" => "callBaz"
                    ],
                    "three" => [
                        "method" => "post",
                        "url" => "baz",
                        "action" => "callBaz"
                    ]
                ],
                array_merge(
                    $this->formatSecurityConfig("one"),
                    $this->formatSecurityConfig("two"),
                    $this->formatSecurityConfig("three")
                ),
                ["foo", "bar", "baz"]
            ],
            "everything together" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "callFoo"),
                        "two" => $this->formatRouteConfig("post", "foo", "mock", "callFoo"),
                        "three" => $this->formatRouteConfig("put", "foo", "mock", "callFoo")
                    ],
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["public" => false],
                            "groups" => [
                                "bar" => [
                                    "url" => "bar",
                                    "security" => ["role" => "user"],
                                    "routes" => [
                                        "four" => $this->formatRouteConfig("get", "bar", "mock", "callBar")
                                    ]
                                ],
                                "baz" => [
                                    "url" => "baz",
                                    "security" => ["role" => "admin"],
                                    "routes" => [
                                        "five" => $this->formatRouteConfig("get", "baz", "mock", "callBaz"),
                                        "six" => $this->formatRouteConfig("post", "baz", "mock", "callBaz", ["role" => "super admin"])
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "two" => [
                        "method" => "post",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "three" => [
                        "method" => "put",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "four" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "five" => [
                        "method" => "get",
                        "url" => "baz",
                        "action" => "callBaz"
                    ],
                    "six" => [
                        "method" => "post",
                        "url" => "baz",
                        "action" => "callBaz"
                    ]
                ],
                array_merge(
                    $this->formatSecurityConfig("one"),
                    $this->formatSecurityConfig("two"),
                    $this->formatSecurityConfig("three"),
                    $this->formatSecurityConfig("four", ["public" => false], ["role" => "user"]),
                    $this->formatSecurityConfig("five", ["public" => false], ["role" => "admin"]),
                    $this->formatSecurityConfig("six", ["public" => false], ["role" => "super admin"])
                ),
                ["foo", "bar", "baz"]
            ]
        ];
    }

    public function invalidRoutesProvider(): array
    {
        return [
            "no url" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "", "mock", "callFoo")
                    ]
                ],
                "/does not contain a URL/"
            ],
            "invalid method" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("put", "foo", "mock", "callFoo")
                    ]
                ],
                "/method.*is not allowed/",
                ["GET", "POST"]
            ],
            "no action" => [
                [
                    "routes" => [
                        "one" => [
                            "method" => "get",
                            "url" => "foo"
                        ]
                    ]
                ],
                "/does not contain an action/"
            ],
            "no controller in action" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "", "callFoo")
                    ]
                ],
                "/does not contain.*controller name/"
            ],
            "no method in action" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "")
                    ]
                ],
                "/does not contain.*controller.*method/"
            ],
            "controller not registered" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "blah", "callFoo")
                    ]
                ],
                "/controller.*not registered/"
            ],
            "controller method doesn't exist" => [
                [
                    "routes" => [
                        "one" => $this->formatRouteConfig("get", "foo", "mock", "missing")
                    ]
                ],
                "/method.*does not exist on controller/"
            ],
            "group contains no routes" => [
                [
                    "groups" => [
                        "url" => "blah"
                    ]
                ],
                "/does not contain any route config/"
            ]
        ];
    }

    public function routeClosureProvider(): array
    {
        return [
            "no args" => [
                "callFoo"
            ],
            "with request" => [
                "addRequest"
            ],
            "with typed request" => [
                "addRequestInterface"
            ],
            "with response" => [
                "addResponse"
            ],
            "with typed response" => [
                "addResponseInterface"
            ],
            "with args" => [
                "addArg",
                ["foo" => "bar"]
            ],
            "with optional arg (present)" => [
                "addOptionalArg",
                ["foo" => "bar"]
            ],
            "with optional arg (missing)" => [
                "addOptionalArg"
            ],
            "with everything" => [
                "addAll",
                ["foo" => "one", "bar" => 2]
            ]
        ];
    }

    protected function formatRouteConfig(string $method, string $url, string $controller, string $action, array $security = [], ?bool $public = null): array
    {
        $config = [
            "method" => $method,
            "url" => $url,
            "action" => [
                "controller" => $controller,
                "method" => $action
            ]
        ];
        if (!empty($security)) {
            $config["security"] = $security;
        }
        if (!is_null($public)) {
            $config["public"] = $public;
        }
        return $config;
    }

    protected function formatSecurityConfig(string $name, array ...$configs): array
    {
        return [$name => array_replace(["public" => true], ...$configs)];
    }

}
