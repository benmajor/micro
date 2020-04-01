<?php

namespace BenMajor\Micro;

use \BenMajor\Micro\Model\ConfigList;
use \Symfony\Component\Yaml\Yaml;

class Plugin
{
	public $name;
	public $version;
	public $contentDirectory;
	public $config;
	
	protected $app;

	function __construct( \BenMajor\Micro\App $app )
	{
		$this->app = $app;

		# Defaults:
		$this->name 	        = 'Default plugin';
		$this->version          = null;
		$this->contentDirectory = null;
		$this->config           = (object) [ ];
		
		$parts = explode('\\', get_class($this)); array_pop($parts);
		
		$this->directory     = $this->app->config->get('dirs.plugins').DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts).DIRECTORY_SEPARATOR;
		$this->viewDirectory = (file_exists($this->directory.'views'.DIRECTORY_SEPARATOR) ? $this->directory.'views'.DIRECTORY_SEPARATOR: $this->directory);
		
		# If there's a config file, parse it:
		if( file_exists($this->directory.'config.yaml') )
		{
			$this->config = new ConfigList($this->directory.'config.yaml');
		}
	}
	
	# Retrieve the plugin's config setting:
	public function getSetting( $key )
	{
		return $this->config->get($key);
	}

	# Get a core setting:
	public function getSiteSetting( $key )
	{
		return $this->app->getSetting($key);
	}
	
	# Get the plugin's content directory:
	public function getContentDirectory()
	{
		if( isset($this->contentDirectory) )
		{
			return (empty($this->contentDirectory) ? null : $this->app->config->get('dirs.content').DIRECTORY_SEPARATOR.'_'.$this->contentDirectory.DIRECTORY_SEPARATOR);
		}
		
		return null;
	}

	# Get the app object:
	public function getApp()
	{
		return $this->app;
	}
	
	# Render a view:
	public function render( $url, array $data )
	{
		return $this->app->render($url, $data);
	}
}