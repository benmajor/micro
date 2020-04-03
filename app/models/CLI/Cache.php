<?php

namespace BenMajor\Micro\CLI;

class Cache extends Command
{
	public function clear()
	{
		$start = microtime();

		echo "\n";
		echo $this->cli->info('Clearing cache, please wait...');
		system('rm -rf cache/');

		$finished = microtime();

		if( is_dir('cache/') )
		{
			throw new \Exception( 'Could not clear cache - maybe the cache dir is not writeable?');
		}
		
		echo $this->cli->success('Time take: '.round($finished - $start, 3).'ms');
		echo $this->cli->success('Micro cache was successfully cleared!');
		echo "\n";

		exit(1);
	}
}