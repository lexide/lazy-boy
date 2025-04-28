<?php

namespace Lexide\LazyBoy\Security;

class ConfigContainer
{

    protected array $routes = [];
    protected array $defaultSecurity = [];

    /**
     * @param array $defaultSecurity
     */
    public function __construct(array $defaultSecurity = [])
    {
        $this->defaultSecurity = $defaultSecurity;
        if (!empty($defaultSecurity)) {
            trigger_error(
                "Setting default security rules in the ConfigContainer is deprecated. " .
                "Please use a base level 'security' key on the config for your routes",
                E_USER_DEPRECATED
            );
        }
    }

    /**
     * @param string $route
     * @param array $security
     */
    public function setSecurityConfigForRoute(string $route, array $security): void
    {
        $this->routes[$route] = $security;
    }

    /**
     * @param string $route
     * @return array
     */
    public function getSecurityConfigForRoute(string $route): array
    {
        return $this->routes[$route] ?? $this->defaultSecurity;
    }

}
