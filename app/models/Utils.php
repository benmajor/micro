<?php

namespace BenMajor\Micro;

class Utils
{
    # Strips the file extension from a string and returns the name
    # Optionally returns only the basename:
    public static function removeExtension( string $filename, bool $basenameOnly = true ): string
    {
        if( $basenameOnly )
        {
            $filename = basename($filename);
        }
        
        return pathinfo($filename, PATHINFO_FILENAME);
    }
    
    # This is a function that extends the jQuery $.extend function:
    public static function extendArray( array $arr1, array $arr2 = [ ])
    {
        if( empty($arr1) )
        {
            return $arr2;
        }
        elseif( empty($arr2) )
        {
            return $arr1;
        }
        
        foreach( $arr2 as $key => $value )
        {
            if( is_int($key) )
            {
                $arr1[] = $value;
            }
            elseif( is_array($arr2[$key]) )
            {
                if( !isset($arr1[$key]) )
                {
                    $arr1[$key] = array();
                }
                
                if( is_int($key) )
                {
                    $arr1[] = array_extend($arr1[$key], $value);
                }
                else
                {
                    $arr1[$key] = array_extend($arr1[$key], $value);
                }
            }
            else
            {
                $arr1[$key] = $value;
            }
        }
        
        return $arr1;
    }

    # Remove the domain from a string (optionally removes a specific domain)
    public static function removeDomainFromURL( string $url, string $domain = null )
    {
        $domain = parse_url( $url, PHP_URL_HOST );

        return substr(
            str_replace([ 'http://', 'https://' ], null, $url), 
            strlen($domain)
        );

    }   

    # Get the current URL (from the address bar)
    # TODO: fix so it's PSR-7 compliant:
    public static function getURL()
    {
        return ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) == 'on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    }
}