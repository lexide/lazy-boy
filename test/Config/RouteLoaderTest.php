<?php

namespace Lexide\LazyBoy\Test\Config;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Mock;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\TestCase;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\Route;
use Lexide\LazyBoy\Config\RouteLoader;
use Lexide\LazyBoy\Exception\RouteException;
use Lexide\Syringe\Loader\JsonLoader;
use Lexide\Syringe\Loader\YamlLoader;
use Lexide\LazyBoy\Security\SecurityContainer;

class RouteLoaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var Application|Mock
     */
    protected $app;

    /**
     * @var SecurityContainer|Mock
     */
    protected $securityContainer;

    /**
     * @var ControllerCollection|Mock $controllerCollection
     */
    protected $controllerCollection;

    /**
     * @var Route|Mock
     */
    protected $route;

    public function setUp(): void
    {
        $this->route = \Mockery::mock("Silex\\Route");
        $this->controllerCollection = \Mockery::mock("Silex\\ContainerCollection");
        $this->app = \Mockery::mock("Silex\\Application");
        $this->securityContainer = \Mockery::mock("Lexide\\LazyBoy\\Security\\SecurityContainer");

        vfsStreamWrapper::register();
    }

    /**
     * @dataProvider routeProvider
     *
     * @param array $routes
     * @param string $exceptionPattern
     * @param array $expectedCalls
     * @throws RouteException
     */
    public function testRouteLoading(array $routes, $exceptionPattern, array $expectedCalls = []) {

        $this->controllerCollection->shouldReceive("bind");

        $loader = new RouteLoader($this->app, $this->securityContainer, ["get", "post"]);

        foreach ($expectedCalls as $call) {
            $this->app->shouldReceive("match")->with($call["url"], $call["action"])->once()->andReturn($this->route);
            $this->route->shouldReceive("method")->with(strtoupper($call["method"]))->once()->andReturn($this->controllerCollection);
        }

        try {
            $loader->parseRoutes($routes);
            if (!empty($exceptionPattern)) {
                $this->fail("The RouteLoader did not throw an exception as expected");
            } elseif (empty($expectedCalls)) {
                $this->expectNotToPerformAssertions();
            }
        } catch (RouteException $e) {
            if (empty($exceptionPattern)) {
                throw $e;
            } else {
                $this->assertMatchesRegularExpression($exceptionPattern, $e->getMessage());
            }
        }

    }

    public function testRouteFileLoading() {

        $counts = [];
        $this->controllerCollection->shouldReceive("bind")->andReturnUsing(function($routeName) use (&$counts) {
            if (empty($counts[$routeName])) {
                $counts[$routeName] = 0;
            }
            ++$counts[$routeName];
        });

        $this->app->shouldReceive("match")->andReturn($this->route);
        $this->route->shouldReceive("method")->with("GET")->andReturn($this->controllerCollection);
        $this->route->shouldReceive("method")->with("POST")->andReturn($this->controllerCollection);

        $loader = new RouteLoader($this->app, $this->securityContainer, ["get", "post"]);
        $loader->addLoader(new JsonLoader());
        $loader->addLoader(new YamlLoader());

        try {
            $loader->parseRoutes(123);
            $this->fail("Should not be able to parse routes with an invalid routes argument");
        } catch (\Exception $e) {
            $this->assertInstanceOf("\\InvalidArgumentException", $e);
            unset($e);
        }

        try {
            $loader->parseRoutes("nonExistentFile");
            $this->fail("Should not be able to parse routes with a non existent file");
        } catch (\Exception $e) {
            $this->assertInstanceOf("\\Lexide\\LazyBoy\\Exception\\RouteException", $e);
            $this->assertMatchesRegularExpression("/Cannot load routes/", $e->getMessage());
            unset($e);
        }

        vfsStreamWrapper::setRoot(new vfsStreamDirectory("test", 0777));

        $routesFile = vfsStream::url("test/test.json");
        $file = vfsStream::newFile("test.json", 0777);
        $file->setContent("not JSON");
        vfsStreamWrapper::getRoot()->addChild($file);

        try {
            $loader->parseRoutes($routesFile);
            $this->fail("Should not be able to parse routes from an invalid JSON file");
        } catch (\Exception $e) {
            $this->assertInstanceOf("\\Lexide\\LazyBoy\\Exception\\RouteException", $e);
            $this->assertMatchesRegularExpression("/Could not load the JSON file/", $e->getMessage());
            unset($e);
        }

        // Test that we can correctly parse JSON
        $url = "url";
        $action = "action";
        $jsonRoute = "jsonRoute";
        $content = json_encode(["routes" => [$jsonRoute => ["url" => $url, "action" => $action]]]);
        $file->setContent($content);
        $loader->parseRoutes($routesFile);


        $routesFile = vfsStream::url("test/test.yaml");
        $file = vfsStream::newFile("test.yaml", 0777);
        $file->setContent("- \"Invalid Yaml");;
        vfsStreamWrapper::getRoot()->addChild($file);

        try{
            $loader->parseRoutes($routesFile);
            $this->fail("Should not be able to parse routes from an invalid Yaml file");
        } catch (\Exception $e) {
            $this->assertInstanceOf("\\Lexide\\LazyBoy\\Exception\\RouteException", $e);
            $this->assertMatchesRegularExpression("/Could not load the YAML file/", $e->getMessage());
        }

        // Test that we can correctly parse YAML
        $url = "url";
        $action = "action";
        $yamlRoute = "yamlRoute";
        $content ="routes:\n  $yamlRoute:\n    url: ".$url."\n    action: ".$action;
        $file->setContent($content);
        $loader->parseRoutes($routesFile);

        $this->assertArrayHasKey($jsonRoute, $counts, "The route in the JSON file was not parsed");
        $this->assertEquals(1, $counts[$jsonRoute], "The route in the JSON file was not parsed exactly once");
        $this->assertArrayHasKey($yamlRoute, $counts, "The route in the YAML file was not parsed");
        $this->assertEquals(1, $counts[$yamlRoute], "The route in the YAML file was not parsed exactly once");
    }

    public function routeProvider()
    {
        return [
            [ #0 no routes or groups
                ["invalid" => "root"],
                "/routes/"
            ],
            [ #1 "routes" is not an array
                ["routes" => "not an array"],
                "/not in the correct format/"
            ],
            [ #2 route missing an action
                [
                    "routes" => [
                        ["url" => "url"]
                    ]
                ],
                "/route is missing required elements/"
            ],
            [ #3 route missing a url
                [
                    "routes" => [
                        ["action" => "action"]
                    ]
                ],
                "/route is missing required elements/"
            ],
            [ #4 route has invalid method
                [
                    "routes" => [
                        [
                            "url" => "url",
                            "action" => "action",
                            "method" => "invalid"
                        ]
                    ]
                ],
                "/The method .* is not allowed/"
            ],
            [ #5 valid route with method
                [
                    "routes" => [
                        [
                            "url" => "url",
                            "action" => "action",
                            "method" => "post"
                        ]
                    ]
                ],
                "",
                [
                    [
                        "method" => "post",
                        "url" => "url",
                        "action" => "action"
                    ]
                ]
            ],
            [ #6 valid route, implicit GET
                [
                    "routes" => [
                        [
                            "url" => "url",
                            "action" => "action"
                        ]
                    ]
                ],
                "",
                [
                    [
                        "method" => "get",
                        "url" => "url",
                        "action" => "action"
                    ]
                ]
            ],
            [ #7 "groups" is not an array
                ["groups" => "not an array"],
                "/not in the correct format/"
            ],
            [ #8 no url for a group
                [
                    "groups" => [
                        "group" => ["no" => "url"]
                    ]
                ],
                "/does not have a URL/"
            ],
            [ #9 exceptions thrown when recursing contain the url prefix
                [
                    "groups" => [
                        "group" => [
                            "urlPrefix" => "blah",
                            "routes" => "not an array"
                        ]
                    ]
                ],
                "/'blah'/"
            ],
            [ #10 valid group
                [
                    "groups" => [
                        "groupOne" => [
                            "urlPrefix" => "foo",
                            "routes" => [
                                "one" => [
                                    "url" => "bar",
                                    "action" => "action"
                                ],
                                "two" => [
                                    "method" => "post",
                                    "url" => "baz",
                                    "action" => "action"
                                ],
                            ]
                        ],
                        "groupTwo" => [
                            "urlPrefix" => "fizz",
                            "routes" => [
                                "three" => [
                                    "url" => "buzz",
                                    "action" => "action"
                                ]
                            ]
                        ]
                    ]
                ],
                "",
                [
                    [
                        "method" => "get",
                        "url" => "foobar",
                        "action" => "action"
                    ],
                    [
                        "method" => "post",
                        "url" => "foobaz",
                        "action" => "action"
                    ],
                    [
                        "method" => "get",
                        "url" => "fizzbuzz",
                        "action" => "action"
                    ]
                ]
            ],
            [ #11 grouped routes that have no URL of their own
                [
                    "groups" => [
                        "groupOne" => [
                            "urlPrefix" => "blah",
                            "routes" => [
                                "one" => [
                                    "url" => "",
                                    "action" => "action"
                                ],
                                "two" => [
                                    "action" => "action",
                                    "method" => "post"
                                ]
                            ]
                        ]
                    ]
                ],
                "",
                [
                    [
                        "method" => "get",
                        "url" => "blah",
                        "action" => "action"
                    ],
                    [
                        "method" => "post",
                        "url" => "blah",
                        "action" => "action"
                    ]
                ]
            ],
            [ #12 nested groups
                [
                    "groups" => [
                        "group" => [
                            "urlPrefix" => "we-",
                            "groups" => [
                                "subgroup" => [
                                    "urlPrefix" => "are-",
                                    "routes" => [
                                        "one" => [
                                            "url" => "legion",
                                            "action" => "action"
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "",
                [
                    [
                        "method" => "get",
                        "url" => "we-are-legion",
                        "action" => "action"
                    ]
                ]
            ]

        ];
    }

    public function testImportingRoutes()
    {
        $overrideUrl = "override.com";

        vfsStreamWrapper::setRoot(new vfsStreamDirectory("test", 0777));

        $routesFile = vfsStream::url("test/test.json");
        $file = vfsStream::newFile("test.json", 0777);
        $content = [
            "imports" => [
                "import1.json",
                "imp/ort2.json"
            ],
            "routes" => [
                "rootFileRoute" => [
                    "url" => "url",
                    "action" => "blah"
                ],
                "overrideRoute" => [
                    "url" => $overrideUrl,
                    "method" => "post",
                    "action" => "blah"
                ]
            ]
        ];
        $file->setContent(json_encode($content));
        vfsStreamWrapper::getRoot()->addChild($file);

        $import1 = vfsStream::newFile("import1.json", 0777);
        $import1Content = [
            "routes" => [
                "import1Route" => [
                    "url" => "url",
                    "action" => "action"
                ],
                "overrideRoute" => [
                    "url" => "shouldn't be this",
                    "method" => "post",
                    "action" => "nor this"
                ]
            ]
        ];
        $import1->setContent(json_encode($import1Content));
        vfsStreamWrapper::getRoot()->addChild($import1);

        $importDir = vfsStream::newDirectory("imp", 0777);
        $import2 = vfsStream::newFile("ort2.json", 0777);
        $import2Content = [
            "routes" => [
                "import2Route" => [
                    "url" => "url",
                    "action" => "action"
                ]
            ],
            "imports" => [
                "import3.json"
            ]
        ];
        $import2->setContent(json_encode($import2Content));
        $importDir->addChild($import2);

        $import3 = vfsStream::newFile("import3.json", 0777);
        $import3Content = [
            "routes" => [
                "import3Route" => [
                    "url" => "url",
                    "action" => "action"
                ]
            ]
        ];
        $import3->setContent(json_encode($import3Content));
        $importDir->addChild($import3);

        vfsStreamWrapper::getRoot()->addChild($importDir);


        // setup mocks
        $counts = [];

        $this->controllerCollection->shouldReceive("bind")->andReturnUsing(function($routeName) use (&$counts) {
            if (empty($counts[$routeName])) {
                $counts[$routeName] = 0;
            }
            ++$counts[$routeName];
        });

        $this->route->shouldReceive("method")->with("GET")->andReturn($this->controllerCollection);
        $this->route->shouldReceive("method")->with("POST")->atLeast()->once()->andReturn($this->controllerCollection);
        // add check to make sure the overridden route uses the correct version (from the "root"-most file)
        $this->app->shouldReceive("match")->withArgs([$overrideUrl, \Mockery::any()])->atLeast()->once()->andReturn($this->route);

        // add default expectation for match last, so it doesn't interfere with the more specific expectation above
        $this->app->shouldReceive("match")->andReturn($this->route);

        // create the loader and run the test
        $loader = new RouteLoader($this->app, $this->securityContainer, ["get", "post"]);
        $loader->addLoader(new JsonLoader());

        $loader->parseRoutes($routesFile);

        $expected = [
            "rootFileRoute" => 1,
            "overrideRoute" => 1,
            "import1Route" => 1,
            "import2Route" => 1,
            "import3Route" => 1,
        ];

        foreach ($expected as $route => $count) {
            $this->assertArrayHasKey($route, $counts, "check the route '$route' was set at all");
            $this->assertEquals($count, $counts[$route], "Check the route '$route' was set x$count");
        }
    }

    public function tearDown(): void
    {
        \Mockery::close();
    }

}
