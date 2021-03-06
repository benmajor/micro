<?php

namespace BenMajor\Micro\CLI;

class Deploy extends Command
{
	public function main()
	{
		# We first need to publish the config file(s):
		$deployConfig = (bool) $this->options->getOpt('config', false);

		if( $deployConfig )
		{
			$confirmDeployConfig = readline('Are you sure you want to deploy all config assets? (y/n) ');
			echo "\n";
		}

		$start = $this->microtime_float();

		# Get the details:
		$hostname = $this->app->config->get('ftp.hostname');
		$username = $this->app->config->get('ftp.username');
		$password = $this->app->config->get('ftp.password');
		$portnum  = $this->app->config->get('ftp.portnum', 21);
		$directory = $this->app->config->get('ftp.directory', 'auto');

		# This is an array of auto-detect web root directories:
		$autoDetectDirs = [
			'public_html',
			'htdocs',
			'www',
			'public'
		];
		
		# No FTP hostname:
		if( ! $this->checkParam($hostname) )
		{
			$this->cli->abort('FTP hostname cannot be null or empty');
		}

		# No FTP username:
		if( ! $this->checkParam($username) )
		{
			$this->cli->abort('FTP username cannot be null or empty');
		}

		$this->cli->writeln('1. Connecting to FTP server...');

		# Try to connect to the FTP server:
		$client = new \FtpClient\FtpClient();

		try
		{
			$client->connect($hostname);
		}
		catch( \Exception $e )
		{
			throw new CLIExpection('Could not connect to FTP host ('.$hostname.')');
		}

		# We're connected to the FTP server:
		$this->cli->success('Connected to FTP server ('.$hostname.')!');
		echo "\n";

		# Now log in:
		$this->cli->writeln('2. Logging into FTP server as '.$username.'...');

		try
		{
			$client->login($username, $password);
		}
		catch( \Exception $e )
		{
			throw new CLIExpection('Failed to login to FTP server, check credentials.');
		}

		# We're logged in:
		$this->cli->success('Logged in to FTP server as '.$username);
		echo "\n";

		# Auto directory:
		if( $directory == 'auto' )
		{
			$this->cli->writeln('3. Detecting web root directory...');

			$foundFlag = false;

			foreach( $autoDetectDirs as $dir )
			{
				if( $client->isDir($dir) && !$foundFlag )
				{
					$directory = $dir;
					$foundFlag = true;
				}
			}

			# Could not detect directory:
			if( ! $foundFlag )
			{
				throw new CLIExpection('Failed to auto-detect FTP root directory');
			}

			$this->cli->success('Detected web root directory as "'.$directory.'"');
		}
		else
		{
			# Check the specified directory is valid:
			$this->cli->writeln('3. Checking specified web root exists...');

			if( ! $client->isDir($directory) )
			{
				throw new CLIExpection('Specified web root directory does not exist!');
			}
		}

		# Now set the CWD as the web root:
		echo "\n";
		$this->cli->writeln('Setting CWD as "'.$directory.'"...');
		$this->cli->success('Successfully set CWD as "'.$directory.'"');
		echo "\n";

		# See if Micro is installed:
		#if( ! $client->isDir($directory.'/app/etc/') || $client->isEmpty($directory.'/app/etc') )
		if( false )
		{
			$this->cli->warning('Micro does not appear to be installed on the remote server!');
			$this->cli->info('Run console/bin install to install Micro');
			$this->cli->abort('Exiting...', false);
		}

		# Define the dirs we need to upload:
		$cwds = [
			'content' => $directory.'/'.$this->app->config->get('dirs.content'),
			 'themes' => $directory.'/'.$this->app->config->get('dirs.view'),
			'plugins' => $directory.'/'.$this->app->config->get('dirs.plugins')
		];

		# Check the directories exist, and ask to create if not:
		foreach( $cwds as $name => $cwd )
		{
			if( ! $client->isDir($cwd) )
			{
				$create = readline(ucfirst($name).' directory does not exist on remote server. Would you like to create it? (y/n) ');

				if( strtolower($create) == 'n' )
				{
					exit(1);
				}
				elseif( strtolower($create) != 'y' )
				{
					throw new CLIExpection('Unexpected response, expected "y" or "n".');
				} 

				# Create the directory:
				$client->mkdir($cwd);
				
				if( ! $client->isDir($cwd) )
				{
					$this->cli->abort('Failed to create '.$name.' directory!');
				}

				$this->cli->success('Created '.$name.' directory on remote server.');
				echo "\n";
			}	
		}

		$this->cli->writeln('4. Deploying to server...');

		# Are we deploying the config file?
		if( isset($confirmDeployConfig) && $confirmDeployConfig )
		{
			echo "\n";
			$this->cli->writeln('Deploying config...');

			try
			{
				$client->putAll('app/etc', $directory.'/app/etc');
				$this->cli->success('Successfully deployed config assets!');
			}
			catch( \Exception $e )
			{
				$this->cli->abort('Failed to deploy config assets! Aborting...');
			}
		}

		foreach( $cwds as $name => $dir )
		{
			$target = $dir;
			$source = ''.basename($dir);

			echo "\n";
			$this->cli->writeln('Deploying '.$name.'...');

			try
			{
				$client->putAll($source, $target);
				$this->cli->success('Successfully deployed '.$name.' assets!');
			}	
			catch( \Exception $e )
			{
				$this->cli->abort('Failed to deploy '.$name.' assets! Aborting...');
			}
		}


		$finished = $this->microtime_float();

		echo "\n";
		echo $this->cli->success('Micro was successfully deployed to '.$hostname.'!');
		echo $this->cli->success('Time taken: '.round($finished - $start, 3).'ms');
		echo "\n";

		exit(1);
	}

	# Check the parameter is valid:
	private function checkParam( $param )
	{
		return ( !is_null($param) && (is_scalar($param) ? strlen($param) : !is_scalar($param)) );
	}
}