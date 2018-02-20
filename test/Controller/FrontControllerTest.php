<?php
namespace Lexide\LazyBoy\Test;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use Lexide\LazyBoy\Controller\FrontController;
use Lexide\LazyBoy\Test\Mocks\MockApplication;
use Lexide\Syringe\ContainerBuilder;
use Lexide\LazyBoy\Config\RouteLoader;
use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;

/**
 *
 */
class FrontControllerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\Mock|ContainerBuilder
     */
    protected $builder;

    /**
     * @var \Mockery\Mock|RouteLoader
     */
    protected $routeLoader;

    /**
     * @var \Mockery\Mock|Application
     */
    protected $application;

    /**
     * @var \Mockery\Mock|ServiceControllerServiceProvider
     */
    protected $serviceProvider;

    public function setUp()
    {
        $this->builder = \Mockery::mock("Lexide\\Syringe\\ContainerBuilder")->shouldIgnoreMissing();
        $this->routeLoader = \Mockery::mock("Lexide\\LazyBoy\\RouteLoader")->shouldIgnoreMissing();
        $this->application = \Mockery::mock("Silex\\Application")->shouldIgnoreMissing();
        $this->serviceProvider = \Mockery::mock("Pimple\\ServiceProviderInterface")->shouldIgnoreMissing();

        vfsStreamWrapper::register();
    }

    public function testSettingApplicationClass()
    {
        // default class
        $class = FrontController::DEFAULT_APPLICATION_CLASS;
        $controller = new FrontController($this->builder, [""], $class);
        $this->assertAttributeEquals($class, "applicationClass", $controller);

        // subclass
        $class = get_class($this->application);
        $controller = new FrontController($this->builder, [""], $class);
        $this->assertAttributeEquals($class, "applicationClass", $controller);

        // invalid class
        try {
            $controller = new FrontController($this->builder, [""], __CLASS__);
            $this->fail("Should not be able to create a FrontController with an invalid application class");
        } catch (\InvalidArgumentException $e) {
        }

    }

    public function testApplicationRun()
    {
        MockApplication::reset();
        $mockApplication = new MockApplication();
        $mockAppClass = get_class($mockApplication);

        $testDir = new vfsStreamDirectory("test", 0777);
        $testDir->addChild(vfsStream::newFile("routes.yml", 0777));
        vfsStreamWrapper::setRoot($testDir);

        $configDir = vfsStream::url("test");

        $mockApplication->setReturn("offsetGet", $this->routeLoader);
        $this->routeLoader->shouldReceive("parseRoutes")->with("$configDir/routes.yml")->once();

        $controller = new FrontController($this->builder, [$configDir], $mockAppClass);
        $controller->runApplication();

        $this->assertEquals("app", $mockApplication->getCalledResponse("offsetSet")[0]);
        $this->assertEquals(["routeLoader"], $mockApplication->getCalledResponse("offsetGet"));
        $this->assertEquals([], $mockApplication->getCalledResponse("run"));
    }

    public function testSettingProviders()
    {
        $providers = [
            $this->serviceProvider,
            $this->serviceProvider,
            $this->serviceProvider,
        ];

        MockApplication::reset();
        $mockApplication = new MockApplication();
        $mockApplication->setReturn("offsetGet", $this->routeLoader);
        $controller = new FrontController($this->builder, ["configDir"], get_class($mockApplication), $providers);
        $controller->runApplication();

        $this->assertEquals(["routeLoader"], $mockApplication->getCalledResponse("offsetGet"));
        $this->assertEquals([$this->serviceProvider], $mockApplication->getCalledResponse("register"));
        $this->assertEquals([$this->serviceProvider], $mockApplication->getCalledResponse("register"));
        $this->assertEquals([$this->serviceProvider], $mockApplication->getCalledResponse("register"));


    }

    public function testRouteFileHandling()
    {

        /* build virtual filesystem

            test/
                config/
                    routes.yml
                vendor/
                    config/
                        routes.yml
                        extra.yml
        */

        vfsStreamWrapper::setRoot(new vfsStreamDirectory("test", 0777));
        $rootDir = vfsStreamWrapper::getRoot();

        $config = new vfsStreamDirectory("config", 0777);
        $rootDir->addChild($config);

        $vendorConfig = new vfsStreamDirectory("config", 0777);
        $vendor = new vfsStreamDirectory("vendor", 0777);
        $vendor->addChild($vendorConfig);
        $rootDir->addChild($vendor);

        $routes = vfsStream::newFile("routes.yml", 0777);
        $config->addChild($routes);

        $vendorRoutes = vfsStream::newFile("routes.yml", 0777);
        $extraRoutes = vfsStream::newFile("extra.yml", 0777);
        $vendorConfig->addChild($vendorRoutes);
        $vendorConfig->addChild($extraRoutes);

        $configDir = vfsStream::url("test/config");
        $vendorDir = vfsStream::url("test/vendor/config");

        $configPaths = [
            $configDir,
            $vendorDir
        ];

        $application = new MockApplication();
        $application->setReturn("offsetGet", $this->routeLoader);

        $this->routeLoader->shouldReceive("parseRoutes")->with($configDir . "/routes.yml")->once();
        $this->routeLoader->shouldNotReceive("parseRoutes")->with($vendorDir . "/routes.yml");
        $this->routeLoader->shouldReceive("parseRoutes")->with($vendorDir . "/extra.yml")->once();

        $class = get_class($application);
        $controller = new FrontController($this->builder, $configPaths, $class);
        $controller->addRouteFile("extra.yml");

        $controller->runApplication();

    }

    public function tearDown()
    {
        \Mockery::close();
    }

}
