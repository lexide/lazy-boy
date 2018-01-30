<?php

namespace Silktide\LazyBoy\Controller;

use Pimple\ServiceProviderInterface;
use Silex\Api\BootableProviderInterface;
use Silktide\Syringe\ContainerBuilder;
use Silex\Application;
use Silktide\LazyBoy\Config\RouteLoader;
use Silktide\Syringe\SyringeServiceProvider;

/**
 * FrontController - loads routes, builds and runs the application
 */
class FrontController 
{

    const DEFAULT_APPLICATION_CLASS = "Silex\\Application";

    /**
     * @var ContainerBuilder
     */
    protected $builder;

    /**
     * @var string
     */
    protected $configPaths;

    /**
     * @var string
     */
    protected $applicationClass;

    /**
     * @var array
     */
    protected $serviceProviders;

    /**
     * @var array
     */
    protected $routeFiles = ["routes.yml"];

    /**
     * @param ContainerBuilder $builder
     * @param array $configPaths
     * @param string $applicationClass
     * @param array $serviceProviders
     */
    public function __construct(ContainerBuilder $builder, array $configPaths, $applicationClass, array $serviceProviders = [])
    {
        $this->builder = $builder;
        $this->configPaths = $configPaths;
        $this->setApplicationClass($applicationClass);
        $this->setProviders($serviceProviders);
    }

    /**
     * @param string file
     */
    public function addRouteFile($file)
    {
        $this->routeFiles[] = $file;
    }

    /**
     * @param array $files
     */
    public function addRouteFiles(array $files)
    {
        $this->routeFiles = array_merge($this->routeFiles, $files);
    }

    /**
     * @param array $files
     */
    public function setRouteFiles(array $files)
    {
        $this->routeFiles = $files;
    }

    protected function setApplicationClass($applicationClass) {
        if ($applicationClass != self::DEFAULT_APPLICATION_CLASS && !is_subclass_of($applicationClass, self::DEFAULT_APPLICATION_CLASS)) {
            throw new \InvalidArgumentException(sprintf("The class '%s' is not a subclass of '%s'", $applicationClass, self::DEFAULT_APPLICATION_CLASS));
        }
        $this->applicationClass = $applicationClass;
    }

    protected function setProviders(array $providers)
    {
        $this->serviceProviders = [];
        foreach ($providers as $provider) {
            $this->addProvider($provider);
        }
    }

    public function addProvider($provider)
    {
        if ($provider instanceof ServiceProviderInterface || $provider instanceof BootableProviderInterface) {
            $this->serviceProviders[] = $provider;
        }
    }

    public function runApplication()
    {
        // create application
        /**
         * @var $application Application
         */
        $application = new $this->applicationClass();

        $application["app"] = function() use ($application) {
            return $application;
        };

        $syringeServiceProviderIncluded = false;
        // register service controller provider
        foreach ($this->serviceProviders as $provider) {
            $application->register($provider);
            if ($provider instanceof SyringeServiceProvider) {
                $syringeServiceProviderIncluded = true;
            }
        }

        if (!$syringeServiceProviderIncluded) {
            $application->register(new SyringeServiceProvider($this->builder));
        }

        // load routes
        $routeLoader = $application["routeLoader"];
        foreach ($this->routeFiles as $routeFile) {
            /** @var RouteLoader $routeLoader */
            foreach ($this->configPaths AS $configPath) {
                $filePath = $configPath . "/" . $routeFile;
                if (file_exists($filePath)) {
                    $routeLoader->parseRoutes($filePath);
                    break;
                }
            }
        }

        // run the app
        $application->run();
    }

} 
