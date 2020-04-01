<?php

namespace BenMajor\Slim3Controller;

abstract class Controller
{
    protected $app;
    protected $container;
    
    protected $request;
    protected $response;
    
    # This can be used to hold the currently authenticated user within the application.
    # It should be assigned in the calllable function within the __invoke magic method.
    protected $user = false;
    
    function __construct( \Slim\App $app )
    {
        $this->app       = $app;
        $this->container = $this->app->getContainer();
        
        $this->request  = $this->container->request;
        $this->response = $this->container->response;
        
        /* You can add any container elements to the controller so that */
        /* they become available in other controllers by assigning them */
        /* to the protected properties of this class as follows:        */
        /*                                                              */
        /* $this->property = $this->container->get('property');         */
    }
    
    # We need to turn the controller itself into an invokable object:
    function __invoke( string $callable )
    {
        $app        = $this->app;
        $controller = $this;
        $actionName = $callable;
        $self       = $this;

        $callable = function($request, $response, $args) use ($app, $controller, $actionName, $self)
        {
            $container = $app->getContainer();
            
            /* The following is an example of assigning an authenticated user */
            /* to the controller so that it can be accessed from other        */
            /* controllers that inherit from this class. This particular      */
            /* method assumes the use of JWT authentication using the Slim3   */
            /* middleware: https://github.com/tuupola/slim-jwt-auth           */
            /*                                                                */
            /*
            $token = $request->getAttribute('token');
            $user  = R::load('user', $token['uid']);
            $route = '/'.trim( $request->getUri()->getPath(), '/' ).'/';
            
            if( ! $user->id && !in_array($route, $this->config['app']['unsecured-endpoints']) )
            {
                return $self->errorResponse(401, 1003);
            }
            
            $self->user = $user;
            */
            
            if( method_exists($controller, 'setRequest') )
            {
                $controller->setRequest($request);
            }
            
            if( method_exists($controller, 'setResponse') )
            {
                $controller->setResponse($response);
            }
            
            if( method_exists($controller, 'init') )
            {
                $controller->init();
            }

            return call_user_func_array(array($controller, $actionName), $args);
        };

        return $callable;
    }
    
    # Send back a successful response:
    public function response( array $data = [ ], int $status = 200 )
    {
        $response = $data;
        
        if( ! array_key_exists('statusCode', $response) ) 
        {
            $response['statusCode'] = $status;
        }
        
        if( ! array_key_exists('timestamp', $response) ) 
        {
            $response['timestamp'] = date('c');
        }
        
        return $this->response->withStatus($status)->withJSON( $response, $status, JSON_NUMERIC_CHECK );
    }
}