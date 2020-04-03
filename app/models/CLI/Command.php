<?php

namespace BenMajor\Micro\CLI;

use splitbrain\phpcli\Options;

class Command
{	
	protected $cli;
	protected $options;

	function __construct( CLI $cli, Options $options )
	{
		$this->cli = $cli;
		$this->options = $options;
	}
	
}