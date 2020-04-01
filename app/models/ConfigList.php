<?php

namespace BenMajor\Micro\Model;

use \Symfony\Component\Yaml\Yaml;
use \BenMajor\Micro\Exception\ConfigException;

class ConfigList
{
    private $file;
    private $data;
    
    function __construct( string $file )
    {
        $this->file = trim($file);
        $this->data = [  ];
        
        if( empty($this->file) )
        {
            throw new ConfigException('Configuration YAML file must not be empty.');
        }
        
        try
        {
            $this->data = Yaml::parseFile($this->file);
        }
        catch( \Exception $e )
        {
            throw new ConfigException( $e->getMessage() );
        }
    }
    
    # Get a particular object:
    public function get( string $accessor, $default = null )
    {
        $return  = $default;
        
        # We only need to loop if there's a dot:
        if( strstr($accessor, '.') )
        {
            $data = $this->data;
            
            foreach( explode('.', $accessor) as $i => $key )
            {
                if( ! array_key_exists($key, $data) )
                {
                    return $default;
                }
                
                $data = $data[ $key ];
                
                if( $i == count(explode('.', $accessor)) - 1 )
                {
                    $return = $data;
                }
            }
        }
        else
        {
            $return = (isset($this->data[$accessor]) ? $this->data[$accessor] : $default);
        }
        
        return $return;
    }
    
    # Set a particular value:
    public function set( string $accessor, $val )
    {
        if( strstr($accessor, '.') )
        {
            $data = &$this->data;
            
            foreach( explode('.', $accessor) as $key )
            {
                if( ! array_key_exists($key, $data) )
                {
                    return $this;
                }
                
                $data = $data[$key];
            }
            
            $data = $val;
        }
        else
        {
            $this->data[$accessor] = $val;
        }
        
        # Return object to preseve method-chaining:
        return $this;
    }
    
    # Add a new value:
    public function add( string $key, $value = null )
    {
        if( array_key_exists($key, $this->data) )
        {
            throw new ConfigException('A config setting already exists called '.$key);
        }
        
        $this->data[$key] = $value;
        
        # Return object to preserve method chaining:
        return $this;
    }
    
    # Save the file (useful after multiple setting):
    public function saveFile()
    {
        $file = fopen($this->file, 'w');
        fwrite($file, Yaml::dump($this->data));
        fclose($file);
        
        return $this;
    }
}