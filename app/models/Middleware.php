<?php

namespace BenMajor\Micro\Model;

class Middleware
{
	protected $app;

	function __construct( \BenMajor\Micro\App $app )
	{
		$this->app = $app;
	}

	function __invoke( $request, $response, $next )
	{
		$this->app->setCurrentSource( $request->getUri() );
		$this->app->setCurrentURI( $request->getUri() );
		$this->app->setRequest( $request );
		$this->app->setResponse( $response );
		
		return $next($request, $response);
	}
}