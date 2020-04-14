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
        'cache' => 'BenMajor\Micro\CLI\Cache',
        'deploy' => 'BenMajor\Micro\CLI\Deploy',
        'publish' => 'BenMajor\Micro\CLI\Publish'
    ];

    # Get the environment (either 'win' or 'nix'):
    private $env;

    # Pointer to the current app:
    public $app;

    # Color definitions:
    const COLOR_BLACK = '0;30';
    const COLOR_DGREY = '1;30';
    const COLOR_RED = '0;31';
    const COLOR_LRED = '1;31';
    const COLOR_GREEN = '0;32';
    const COLOR_LGREEN = '1;32';
    const COLOR_BROWN = '0;33';
    const COLOR_YELLOW = '1;33';
    const COLOR_BLUE = '0;34';
    const COLOR_LBLUE = '1;34';
    const COLOR_MAGENTA = '0;35';
    const COLOR_LMAGENTA = '1;35';
    const COLOR_CYAN = '0;36';
    const COLOR_LCYAN = '1;36';
    const COLOR_LGREY = '0;37';
    const COLOR_WHITE = '1;37';

    function __construct( \BenMajor\Micro\App $app )
    {
        parent::__construct();

        $this->app = $app;

        # Set the environment:
        $this->env = (stripos(PHP_OS, 'WIN') === 0) ? 'win' : 'nix';
    }

    # Set up our options:
    protected function setup(Options $options)
    {
        $options->setHelp('A very minimal example that does nothing but print a version');

        # Generic options:
        $options->registerOption('version', 'print version', 'v');

        # Cache functions:
        $options->registerCommand('cache:clear', 'Clear the Twig cache.');
        $options->registerCommand('cache:enable', 'Enable the Twig cache.');
        $options->registerCommand('cache:disable', 'Disable the Twig cache.');
        $options->registerCommand('cache:status', 'Retrieve the current status of the Twig cache.');

        # Cache options:
        $options->registerOption('clear', 'Indicate whether or not to clear the cache when enabling.', null, true, 'cache:enable');

        # Deployment / plublishing functions:
        $options->registerCommand('deploy', 'Deploy all assets (config, content, themes and plugins) to the specified FTP server.');
        $options->registerCommand('publish', 'Publish all content to the specified FTP server.');

        # Deploy options:
        $options->registerOption('config', 'Specify whether config files should also be deployed', null, true, 'deploy');
    }

    protected function main( Options $opts )
    {  
        # Check if CLI is enabled:
        if( ! $this->app->config->get('cli.enabled') )
        {
            $this->abort('CLI access is disabled by config.');
        }

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

        if( $this->app->config->get('cli.clearOnCommand') )
        {
            $this->clearConsole();
        }

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
            $className = $command[0];

            # Try to instantiate the class:
            try
            {
                $class = new $this->classMappings[$className]($this, $opts);

                # Check there's a main() method:
                if( ! method_exists($class, 'main') )
                {
                    $this->abort('Specified command is not configured correctly :(');
                }

                # Now try to call the main method:
                try
                {

                    $class->main();
                }
                catch( \Exception $e )
                {
                    $this->abort( $e->getMessage() );
                }

            }
            catch( \Exception $e )
            {
                $this->abort('Class not found: '.$className);
            }
        }

        # No command:
        else
        {
            echo $opts->help();
        }
    }

    # Write a line:
    public function writeln( string $message, $color = null )
    {
        $this->output($message, $color, false, true);
    }

    # Send output to the console:
    public function output( string $message, $color = null, $leadingNL = false, $trailingNL = false )
    {
        $colors = [
            'default' => null,
            'black' => self::COLOR_BLACK,
            'grey_dark' => self::COLOR_DGREY,
            'grey_light' => self::COLOR_LGREY,
            'red' => self::COLOR_RED,
            'red_light' => self::COLOR_LRED,
            'green' => self::COLOR_GREEN,
            'green_light' => self::COLOR_LGREEN,
            'brown' => self::COLOR_BROWN,
            'yellow' => self::COLOR_YELLOW,
            'blue' => self::COLOR_BLUE,
            'blue_light' => self::COLOR_LBLUE,
            'magenta' => self::COLOR_MAGENTA,
            'magenta_light' => self::COLOR_LMAGENTA,
            'cyan' => self::COLOR_CYAN,
            'cyan_light' => self::COLOR_LCYAN,
            'white' => self::COLOR_WHITE
        ];

        if( $leadingNL )
        {
            echo "\n";
        }

        echo ($color != null && $color != 'default' && array_key_exists($color, $colors)) ? "\e[".$colors[$color].'m'.$message."\e[0m" : $message;

        if( $trailingNL )
        {
            echo "\n";
        }
    }

    # Send an error back to the CLI:
    public function abort( string $message, $leadingNL = true )
    {
        if( $leadingNL )
        {
            echo "\n";  
        }

        echo $this->error($message);
        echo "\n";
        exit(1);
    }

    # Function to clear the console:
    public function clearConsole()
    {
        $cmd = ($this->env == 'win') ? 'cls' : 'clear';
        system($cmd);
    }
}