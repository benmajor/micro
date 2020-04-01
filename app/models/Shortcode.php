<?php

namespace BenMajor\Micro\Model;

class Shortcode
{
    public $tag;
    public $callback;
    
    public $regex      = '/\[([a-zA-Z_-]+)\s?(.*?)\](.*(?=\[))?(\[\/([a-zA-Z_-]+)\])?/m';
    private $attrRegex = '/(\w+)="([^"]+)"/m'; 
    
    private $app;
    
    function __construct( $tag, $callback, $app )
    {
        $this->tag      = $tag;
        $this->callback = $callback;
        $this->app      = $app;
    }
    
    # Retrieve the tag:
    public function getTag()
    {
        return $this->tag;
    }
    
    # Execute whatever callback is defined, by passing in the matching attributes (and optional content):
    public function execCallback( array $attributes, $content )
    {
        return call_user_func($this->callback, $attributes, $content);
        
    }
    
    # Parse text using the current shortcode:
    public function parseText( string $text )
    {
        $parsed  = $text;
        $replace = [ ];
        
        # Parse it:
        preg_match_all($this->regex, $text, $result, PREG_SET_ORDER, 0);
        
        # Loop over the matches:
        foreach( $result as $r )
        {
            $content    = (count($r) > 3) ? $this->app->parseShortcodes($r[3]) : null;
            $attributes = [ ];
            
            # Parse the attributes:
            if( ! empty($r[2]) )
            {
                preg_match_all($this->attrRegex, trim(str_replace('&quot;', '"', $r[2])), $attResult);
                
                $attributes = (empty($attResult[1])) ? [ ] : array_combine(array_filter($attResult[1]), $attResult[2]);
            }
            
            $replace[] = $this->execCallback( $attributes, $content );
        }
        
        # Loop over and replace it:
        foreach( $result as $i => $r )
        {
            $parsed = str_replace($r[0], $replace[$i], $parsed);
        }
        
        return $parsed;
    }
}