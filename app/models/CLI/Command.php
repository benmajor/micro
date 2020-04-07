<?php

namespace BenMajor\Micro\CLI;

use splitbrain\phpcli\Options;

class Command
{	
	protected $app;
	protected $cli;
	protected $options;

	function __construct( CLI $cli, Options $options )
	{
		$this->app = $cli->app;
		$this->cli = $cli;
		$this->options = $options;
	}

	# Get the microtime as a float (for math operations):
	protected function microtime_float()
	{
	    list($usec, $sec) = explode(' ', microtime());
	    return ((float)$usec + (float)$sec);
	}	
}