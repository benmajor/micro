<?php

namespace BenMajor\Micro\CLI;

class Cache extends Command
{
	public function clear()
	{
		$start = $this->microtime_float();

		echo "\n";
		echo $this->cli->info('Clearing cache, please wait...');
		system('rm -rf cache/');

		$finished = $this->microtime_float();

		if( is_dir('cache/') )
		{
			throw new \Exception( 'Could not clear cache - maybe the cache dir is not writeable?');
		}
		
		echo $this->cli->success('Micro cache was successfully cleared!');
		echo $this->cli->success('Time taken: '.round($finished - $start, 3).'ms');
		echo "\n";

		exit(1);
	}

	# Enable the cache:
	public function enable( $clear = true )
	{
		if( (bool) $this->options->getOpt('clear', false) )
		{
			$this->clear();
		}

		try
		{
			$this->app->config->set('twig.cache', true);
			$this->app->config->saveFile();
			$this->cli->success('Cache successfully enabled');
		}	
		catch( \Exception $e )
		{
			$this->cli->abort('Could not enable cache! Aborting...');
		}
	}

	# Disable the cache:
	public function disable()
	{
		try
		{
			$this->app->config->set('twig.cache', false);
			$this->app->config->saveFile();
			$this->cli->success('Cache successfully disabled');
		}	
		catch( \Exception $e )
		{
			$this->cli->abort('Could not disnable cache! Aborting...');
		}
	}

	# Get the status of the cache:
	public function status()
	{
		$str = ($this->app->config->get('twig.cache')) ? 'ENABLED' : 'DISABLED';
		$opp = ($this->app->config->get('twig.cache')) ? 'disable' : 'enable';

		$this->cli->info('Twig cache is currently '.$str);
		$perform = readline('Would you like to '.$opp.' it? (y/n) ');

		if( strtolower($perform) == 'y' )
		{
			$this->$opp();
		}
	}

}