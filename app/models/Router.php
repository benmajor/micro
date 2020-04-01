<?php

namespace BenMajor\Micro\Model;

class Router
{
    protected $namespace   = '';
    protected $classSuffix = '';
    protected $classes     = [ ];
    protected $routes      = [ ];
    
    private $app;
    private $config;
    
    function __construct( array $routes, \BenMajor\Micro\App $app )
    {
        $this->routes = $routes;
        $this->app    = $app;
        $this->config = $this->app->config;
        
        $this->namespace   = '\\BenMajor\\Micro\\Controller\\';
        
        # Get the distinct classes:
        foreach( $this->routes as $path => $route )
        {
            if( !isset($route['active']) || $route['active'] === true )
            {
                $this->addRoute($path, $route);
            }
        }
    }
    
    # Add a new route:
    public function addRoute( string $path, array $route )
    {
        $controller = null;
        
        if( !array_key_exists($route['controller'], $this->classes) )
        {
            $controllerName = $this->namespace.$route['controller'].$this->classSuffix;
            
            # Create a new class and add it to the available class list:
            $this->classes[$route['controller']] = $controller = new $controllerName( $this->_app );
        }
        else
        {
            $controller = $this->classes[$route['controller']];
        }
        
        foreach( $route['methods'] as $httpMethod => $method )
        {
            $this->app->getSlim()->{$httpMethod}($path, $controller($method['method']))->setName( $method['name'] );
        }
    }
}