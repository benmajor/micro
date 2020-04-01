<?php

namespace BenMajor\Micro;

use \BenMajor\Micro\Exception\PluginException;

define( 'SPL_CONTROLLER_PREFIX', 'BenMajor\\Micro\\Controller');

spl_autoload_register(function( $class ) {
    
    # It's a controller:
    if( substr($class, 0, strlen(SPL_CONTROLLER_PREFIX)) == SPL_CONTROLLER_PREFIX )
    {
        
        $filename = strtolower(str_replace('Controller\\', '', substr($class, 15))).'.php';
        require_once 'app/controllers/'.$filename;
        #filename = 
    }
    
    # It's a core class:
    elseif( substr($class, 0, 15) == 'BenMajor\\Micro\\' )
    {
        $filename = str_replace('Model\\', '', substr($class, 15));
        $filename = str_replace('\\', DIRECTORY_SEPARATOR, $filename);

        require_once 'app/models/'.$filename.'.php';
    }

    # It's a plugin?!
    else
    {
        $filename = str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php';
        
        $baseDir       = 'plugins'.DIRECTORY_SEPARATOR.dirname(str_replace([ DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR.'Model'.DIRECTORY_SEPARATOR ], DIRECTORY_SEPARATOR, $filename)).DIRECTORY_SEPARATOR;
        $controllerDir = $baseDir.'controllers'.DIRECTORY_SEPARATOR;
        $modelDir      = $baseDir.'models'.DIRECTORY_SEPARATOR;
        $basename      = basename($filename);
        
        $toLoad        = $baseDir.$basename;
        
        # It's a controller, so check in the controller directory:
        if( strstr($filename, DIRECTORY_SEPARATOR.'Controller'.DIRECTORY_SEPARATOR) )
        {
            if( file_exists($controllerDir) && file_exists($controllerDir.$basename) )
            {
                $toLoad = $controllerDir.$basename;
            }
        }
        else
        {   
            if( file_exists($modelDir.$basename) )
            {
                $toLoad = $modelDir.$basename;
            }
        }
        
        if( ! file_exists($toLoad) )
        {
            throw new PluginException('Plugin autoloader failed. File '.$toLoad.' does not exist.');
        }

        require_once $toLoad;
    }
});