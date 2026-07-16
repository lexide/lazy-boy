<?php

namespace Lexide\LazyBoy\Test\Config;

use Lexide\LazyBoy\Config\RouteLoader;
use Lexide\LazyBoy\Exception\RouteException;
use Lexide\LazyBoy\Security\ConfigContainer;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
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
    protected object $controller;

    public function setUp(): void
    {
        $this->securityContainer = \Mockery::mock(ConfigContainer::class);
        $this->app = \Mockery::mock(App::class);
        $this->route = \Mockery::mock(RouteInterface::class);
        $this->group = \Mockery::mock(RouteGroupInterface::class);
        $this->request = \Mockery::mock(ServerRequestInterface::class);
        $this->response = \Mockery::mock(ResponseInterface::class);
        $this->controller = $this->createController();
    }

    protected function createController(): object
    {
        return new class {

            protected array $callCount = [];

            public function callFoo(): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function callBar(): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function callBaz(): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addRequest($request): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addRequestInterface(RequestInterface $req): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addResponse($response): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addResponseInterface(ResponseInterface $resp): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addArg(string $foo): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addOptionalArg(string $foo = ""): void
            {
                $this->recordCall(__FUNCTION__);
            }

            public function addAll(RequestInterface $request, string $foo, int $bar, ResponseInterface $response): void
            {
                $this->recordCall(__FUNCTION__);
            }

            protected function recordCall(string $function): void
            {
                $this->callCount[$function] = $this->callCount[$function] ?? 0;
                ++$this->callCount[$function];
            }

            public function getCallCount(): array
            {
                return $this->callCount;
            }

        };
    }


    #[DataProvider("routesProvider")]
    public function testSettingRoutes(
        array $routes,
        array $expectedRouteMapping,
        array $expectedSecurityConfig,
        array $expectedGroupMapping = [],
        array $additionalRoutes = []
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
        $loader->addRouteConfig($additionalRoutes);

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

    #[DataProvider("invalidRoutesProvider")]
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

    #[DataProvider("routeClosureProvider")]
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

    public static function routesProvider(): array
    {

        return [
            "simple route" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo")
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                self::formatSecurityConfig("one")
            ],
            "defaulted method" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("", "foo", "mock", "callFoo")
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                self::formatSecurityConfig("one")
            ],
            "multiple routes" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                        "two" => self::formatRouteConfig("post", "bar", "mock", "callBar"),
                        "three" => self::formatRouteConfig("put", "baz", "mock", "callBaz")
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
                    self::formatSecurityConfig("one"),
                    self::formatSecurityConfig("two"),
                    self::formatSecurityConfig("three")
                )
            ],
            "private route" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo", [], false)
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                self::formatSecurityConfig("one", ["public" => false])
            ],
            "custom security" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo", ["foo" => "bar", "user" => 123])
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                self::formatSecurityConfig("one", ["foo" => "bar", "user" => 123])
            ],
            "simple group" => [
                [
                    "groups" => [
                        "foo" => [
                            "routes" => [
                                "one" => self::formatRouteConfig("get", "bar", "mock", "callBar")
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
                self::formatSecurityConfig("one"),
                [""]
            ],
            "group with url" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "routes" => [
                                "one" => self::formatRouteConfig("get", "bar", "mock", "callBar")
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
                self::formatSecurityConfig("one"),
                ["foo"]
            ],
            "group security" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["role" => "admin", "public" => false],
                            "routes" => [
                                "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo")
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
                self::formatSecurityConfig("one", ["role" => "admin", "public" => false]),
                ["foo"]
            ],
            "route security overrides group security" => [
                [
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["role" => "user", "public" => false],
                            "routes" => [
                                "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo", ["role" => "admin"])
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
                self::formatSecurityConfig("one", ["role" => "user", "public" => false], ["role" => "admin"]),
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
                                        "one" => self::formatRouteConfig("get", "bar", "mock", "callBar")
                                    ]
                                ],
                                "baz" => [
                                    "url" => "baz",
                                    "routes" => [
                                        "two" => self::formatRouteConfig("get", "baz", "mock", "callBaz"),
                                        "three" => self::formatRouteConfig("post", "baz", "mock", "callBaz")
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
                    self::formatSecurityConfig("one"),
                    self::formatSecurityConfig("two"),
                    self::formatSecurityConfig("three")
                ),
                ["foo", "bar", "baz"]
            ],
            "adding routes" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
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
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("one"),
                    self::formatSecurityConfig("two")
                ),
                [],
                [
                    "routes" => [
                        "two" => self::formatRouteConfig("post", "bar", "mock", "callBar")
                    ]
                ]
            ],
            "don't overwrite routes" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                    ]
                ],
                [
                    "one" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("one")
                ),
                [],
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("post", "bar", "mock", "callBar")
                    ]
                ]
            ],
            "add groups" => [
                [
                    "groups" => [
                        "one" => [
                            "url" => "one",
                            "routes" =>[
                                "two" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                            ]
                        ]
                    ]
                ],
                [
                    "two" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "four" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("two"),
                    self::formatSecurityConfig("four")
                ),
                ["one", "three"],
                [
                    "groups" => [
                        "three" => [
                            "url" => "three",
                            "routes" =>[
                                "four" => self::formatRouteConfig("get", "bar", "mock", "callBar"),
                            ]
                        ]
                    ]
                ]
            ],
            "add routes to a group" => [
                [
                    "groups" => [
                        "one" => [
                            "url" => "one",
                            "routes" =>[
                                "two" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                            ]
                        ]
                    ]
                ],
                [
                    "two" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ],
                    "three" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("two"),
                    self::formatSecurityConfig("three")
                ),
                ["one"],
                [
                    "groups" => [
                        "one" => [
                            "url" => "one",
                            "routes" =>[
                                "three" => self::formatRouteConfig("get", "bar", "mock", "callBar"),
                            ]
                        ]
                    ]
                ]
            ],
            "don't change url or security for a group" => [
                [
                    "groups" => [
                        "one" => [
                            "url" => "foo",
                            "security" => ["public" => false],
                            "routes" =>[
                                "two" => self::formatRouteConfig("get", "bar", "mock", "callBar"),
                            ]
                        ]
                    ]
                ],
                [
                    "two" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "three" => [
                        "method" => "get",
                        "url" => "baz",
                        "action" => "callBaz"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("two", ["public" => false]),
                    self::formatSecurityConfig("three", ["public" => false])
                ),
                ["foo"],
                [
                    "groups" => [
                        "one" => [
                            "url" => "fiz",
                            "security" => ["public" => true],
                            "routes" =>[
                                "three" => self::formatRouteConfig("get", "baz", "mock", "callBaz"),
                            ]
                        ]
                    ]
                ]
            ],
            "don't overwrite routes within a group" => [
                [
                    "groups" => [
                        "one" => [
                            "url" => "one",
                            "routes" =>[
                                "two" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                            ]
                        ]
                    ]
                ],
                [
                    "two" => [
                        "method" => "get",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("two")
                ),
                ["one"],
                [
                    "groups" => [
                        "one" => [
                            "url" => "one",
                            "routes" =>[
                                "two" => self::formatRouteConfig("get", "bar", "mock", "callBar"),
                            ]
                        ]
                    ]
                ]
            ],
            "everything together" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "callFoo"),
                        "two" => self::formatRouteConfig("post", "foo", "mock", "callFoo"),
                        "three" => self::formatRouteConfig("put", "foo", "mock", "callFoo")
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
                                        "five" => self::formatRouteConfig("get", "bar", "mock", "callBar")
                                    ]
                                ],
                                "baz" => [
                                    "url" => "baz",
                                    "security" => ["role" => "admin"],
                                    "routes" => [
                                        "seven" => self::formatRouteConfig("get", "baz", "mock", "callBaz"),
                                        "eight" => self::formatRouteConfig("post", "baz", "mock", "callBaz", ["role" => "super admin"])
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
                        "method" => "patch",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "five" => [
                        "method" => "get",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "six" => [
                        "method" => "post",
                        "url" => "bar",
                        "action" => "callBar"
                    ],
                    "seven" => [
                        "method" => "get",
                        "url" => "baz",
                        "action" => "callBaz"
                    ],
                    "eight" => [
                        "method" => "post",
                        "url" => "baz",
                        "action" => "callBaz"
                    ],
                    "nine" => [
                        "method" => "delete",
                        "url" => "foo",
                        "action" => "callFoo"
                    ]
                ],
                array_merge(
                    self::formatSecurityConfig("one"),
                    self::formatSecurityConfig("two"),
                    self::formatSecurityConfig("three"),
                    self::formatSecurityConfig("four"),
                    self::formatSecurityConfig("five", ["public" => false], ["role" => "user"]),
                    self::formatSecurityConfig("six", ["public" => false], ["role" => "user"]),
                    self::formatSecurityConfig("seven", ["public" => false], ["role" => "admin"]),
                    self::formatSecurityConfig("eight", ["public" => false], ["role" => "super admin"]),
                    self::formatSecurityConfig("nine", ["public" => false], ["role" => "editor"])
                ),
                ["foo", "bar", "baz", "fiz"],
                [
                    "routes" => [
                        "two" => self::formatRouteConfig("post", "bar", "mock", "callBar"),
                        "four" => self::formatRouteConfig("patch", "bar", "mock", "callBar")
                    ],
                    "groups" => [
                        "foo" => [
                            "url" => "foo",
                            "security" => ["public" => false],
                            "groups" => [
                                "bar" => [
                                    "url" => "bar",
                                    "routes" => [
                                        "six" => self::formatRouteConfig("post", "bar", "mock", "callBar")
                                    ]
                                ],
                                "baz" => [
                                    "url" => "foo",
                                    "routes" => [
                                        "eight" => self::formatRouteConfig("post", "baz", "mock", "callBaz", ["role" => "user"])
                                    ]
                                ],
                                "fiz" => [
                                    "url" => "fiz",
                                    "security" => ["role" => "editor"],
                                    "routes" => [
                                        "nine" => self::formatRouteConfig("delete", "foo", "mock", "callFoo")
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]//*/
        ];
    }

    public static function invalidRoutesProvider(): array
    {
        return [
            "no url" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "", "mock", "callFoo")
                    ]
                ],
                "/does not contain a URL/"
            ],
            "invalid method" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("put", "foo", "mock", "callFoo")
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
                        "one" => self::formatRouteConfig("get", "foo", "", "callFoo")
                    ]
                ],
                "/does not contain.*controller name/"
            ],
            "no method in action" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "")
                    ]
                ],
                "/does not contain.*controller.*method/"
            ],
            "controller not registered" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "blah", "callFoo")
                    ]
                ],
                "/controller.*not registered/"
            ],
            "controller method doesn't exist" => [
                [
                    "routes" => [
                        "one" => self::formatRouteConfig("get", "foo", "mock", "missing")
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

    public static function routeClosureProvider(): array
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

    protected static function formatRouteConfig(string $method, string $url, string $controller, string $action, array $security = [], ?bool $public = null): array
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

    protected static function formatSecurityConfig(string $name, array ...$configs): array
    {
        return [$name => array_replace(["public" => true], ...$configs)];
    }

}
