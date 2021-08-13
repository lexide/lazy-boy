<?php

namespace Lexide\LazyBoy\Config;

use Silex\Application;
use Silex\Controller;
use Lexide\LazyBoy\Exception\RouteException;
use Lexide\LazyBoy\Security\SecurityContainer;
use Lexide\Syringe\Exception\LoaderException;
use Lexide\Syringe\Loader\LoaderInterface;

/**
 * Load routes into the application
 */
class RouteLoader 
{

    /**
     * @var array
     */
    protected $allowedMethods;

    /**
     * @var Application
     */
    protected $application;

    /**
     * @var LoaderInterface[]
     */
    protected $loaders = [];

    /**
     * @var SecurityContainer
     */
    protected $securityContainer = [];

    /**
     * @param Application $application
     * @param SecurityContainer $securityContainer
     * @param array $allowedMethods
     * @param array $loaders
     */
    public function __construct(
        Application $application,
        SecurityContainer $securityContainer,
        array $allowedMethods,
        array $loaders = []
    ) {
        $this->application = $application;
        $this->securityContainer = $securityContainer;
        $this->setAllowedMethods($allowedMethods);

        foreach ($loaders as $loader) {
            if ($loader instanceof LoaderInterface) {
                $this->addLoader($loader);
            }
        }
    }

    /**
     * @param array $allowedMethods
     */
    protected function setAllowedMethods(array $allowedMethods)
    {
        $this->allowedMethods = array_flip( // flip so we can use isset()
            array_map( // normalise values to uppercase
                "strtoupper",
                $allowedMethods
            )
        );
    }

    /**
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * @param array|string $routes - route data or filePath to route data
     * @param string $baseUrl - used to track the base URL within a group of routes. This argument should only be required for internal recursion
     * @throws RouteException
     */
    public function parseRoutes($routes, $baseUrl = "")
    {
        // if we don't have a data array, see if we can load it from a file
        if (!is_array($routes)) {
            $routes = $this->loadFile($routes);
        }

        // process groups
        if (!empty($routes["groups"])) {
            if (!is_array($routes["groups"])) {
                throw new RouteException("The group data array for '$baseUrl' is not in the correct format");
            }

            foreach ($routes["groups"] as $groupName => $config) {
                if (empty($config["urlPrefix"])) {
                    throw new RouteException("The group '$groupName' does not have a URL associated with it");
                }
                $this->parseRoutes($config, $baseUrl . $config["urlPrefix"]);
            }

        }

        // validation
        if (!empty($routes["routes"])) {
            if (!is_array($routes["routes"])) {
                throw new RouteException("The routes data array for '$baseUrl' is not in the correct format");
            }

            foreach ($routes["routes"] as $routeName => $config) {
                // build the URL
                $url = $baseUrl . (isset($config["url"])? $config["url"]: "");

                // route validation
                if (empty($url) || empty($config["action"])) {
                    throw new RouteException("The data for the '$routeName' route is missing required elements");
                }
                if (empty($config["method"])) {
                    $config["method"] = "GET";
                } else {
                    $config["method"] = strtoupper($config["method"]);
                    // check method is allowed
                    if (!isset($this->allowedMethods[$config["method"]])) {
                        throw new RouteException("The method '{$config["method"]}' for route '$routeName' is not allowed");
                    }
                }
                // add the route
                /**
                 * @var Controller $controller
                 */
                $controller = $this->application
                    ->match($url, $config["action"])
                    ->method($config["method"])
                    ->bind($routeName);

                if (isset($config["assert"]) && is_array($config["assert"])) {
                    foreach ($config["assert"] as $variable => $regex) {
                        $controller->assert($variable, $regex);
                    }
                }

                // apply security if required
                $security = null;
                if (isset($config["public"])) {
                    $security = ["public" => $config["public"]];
                } elseif (!empty($config["security"])) {
                    $security = $config["security"];
                }
                if ($security !== null) {
                    $this->securityContainer->setSecurityForRoute($routeName, $security);
                }
            }
        }

        if (empty($routes["routes"]) && empty($routes["groups"])) {
            throw new RouteException("No routes or groups were found for '$baseUrl'");
        }

    }


    /**
     * @param $file
     * @return LoaderInterface
     * @throws \Exception||LoaderException
     */
    protected function selectLoader($file)
    {
        foreach ($this->loaders as $loader) {
            /** @var LoaderInterface $loader */
            if ($loader->supports($file)) {
                return $loader;
            }
        }
        throw new LoaderException(sprintf("The file '%s' is not supported by any of the available loaders", $file));
    }

    protected function loadFile($routes)
    {
        if (!is_string($routes)) {
            throw new \InvalidArgumentException("The \$routes argument must be an array or a filePath");
        }

        $filePath = $routes;
        // check $routes is a filePath
        if (!file_exists($filePath)) {
            throw new RouteException("Cannot load routes, the file '$filePath' does not exist");
        }

        try{
            $loader = $this->selectLoader($filePath);
            $routes = $loader->loadFile($filePath);
        } catch(LoaderException $e) {
            throw new RouteException($e->getMessage());
        }
        // load any route files we've been asked to import
        if (!empty($routes["imports"])) {
            // the import file will be relative to this file, so get the file's directory
            $rootPath = substr($filePath, 0, strrpos($filePath, "/") + 1);
            foreach ($routes["imports"] as $import) {
                // load the import and merge with the route file
                $importedRoutes = $this->loadFile($rootPath . $import);

                $routes = array_replace_recursive($importedRoutes, $routes);
            }
        }
        return $routes;
    }

} 
