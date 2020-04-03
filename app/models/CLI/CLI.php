<?php

namespace BenMajor\Micro\CLI;

use splitbrain\phpcli\CLI as PHPCLI;
use splitbrain\phpcli\Options;

class CLI extends PHPCLI
{
    # Define the version of the CLI:
    private $version = '1.0.0';

    # This is our mapping of 
    private $classMappings = [
        'cache' => 'BenMajor\Micro\CLI\Cache'
    ];

    # Pointer to the current app:
    private $app;

    function __construct( \BenMajor\Micro\App $app )
    {
        parent::__construct();

        $this->app = $app;
    }

    # Set up our options:
    protected function setup(Options $options)
    {
        $options->setHelp('A very minimal example that does nothing but print a version');

        $options->registerOption('version', 'print version', 'v');
        $options->registerOption('env', 'Cache environment to clear', 'e');

        # Cache functions:
        $options->registerCommand('cache:clear', 'Clear the cache');
    }

    protected function main( Options $opts )
    {  
        # Do we have an option?
        if( $opts->getOpt('version') )
        {
            echo "\n";
            echo $this->info( 'Version info:' );
            echo $this->info( '-------------' );
            echo $this->info( 'Current CLI version: '.$this->version );
            echo $this->info( 'Current Micro version: '.$this->app->getVersion() );
            echo "\n";
            exit(1);
        }

        $command = array_filter(explode(':', $opts->getCmd()));
        $options = $opts->getArgs();

        # Two-part command:
        if( count($command) == 2 )
        {
            $className = $command[0];
            $fn = $command[1];

            if( ! array_key_exists($className, $this->classMappings) )
            {
                $this->abort('Invalid command: '.$opts->getCmd());
            }

            $class = new $this->classMappings[$className]($this, $opts);

            if( ! method_exists($class, $fn) )
            {
                $this->abort('Invalid command: '.$this->getCmd());
            }

            try
            {
                $class->$fn();
            }
            catch( \Exception $e )
            {
                $this->abort( $e->getMessage() );
            }
        }

        # Single commands:
        elseif( count($command) )
        {

        }

        # No command:
        else
        {
            echo $opts->help();
        }
    }

    # Send an error back to the CLI:
    private function abort( string $message )
    {
        echo "\n";
        echo $this->error($message);
        echo "\n";
        exit(1);
    }
}