<?php

namespace BenMajor\Micro\Model;

use \Symfony\Component\Yaml\Yaml;

class ContentItem
{
    public $app;
    public $published = true;
    public $meta;
    public $type;
    
    protected $sourceFilename;
    protected $sourceBasename;
    protected $sourceExtension;
    protected $route;
    protected $routeInfo = [ ];
    protected $controller;
    
    private $config;
    
    function __construct( \SplFileObject $src, \BenMajor\Micro\App $app )
    {
        $this->app    = $app;
        $this->config = $this->app->config;
        $this->meta   = [ ];
        $this->title  = null;
        $this->type   = 'page'; # This is the default type - it might be overwritten.
        
        # Get the basic information:
        $this->source          = $src;
        $this->sourceFilename  = $src->getFilename();
        $this->sourceBasename  = basename($this->sourceFilename);
        $this->sourceName      = pathinfo($this->sourceFilename, PATHINFO_FILENAME);
        $this->sourceExtension = pathinfo($this->sourceFilename, PATHINFO_EXTENSION);
        
        # Build the route:
        $this->routeInfo = [
            'path'     => '/'.ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($this->source->getPath(), strlen($this->app->config->get('dirs.content')))), '/'),
            'filename' => ($this->sourceName == 'index') ? '[/]' : '/'.$this->sourceName.$this->app->config->get('router.filenameSuffix')
        ];
        
        # Check if the first line == ---:
        if( trim($this->source->current()) == '---' )
        {
            $this->hasMeta = true;
            
            # Now read the meta:
            $metaStr = [ ];
            $lineNum = 0;
            
            while( ! $this->source->eof() )
            {
                $line = trim($this->source->fgets());

                if( $line == '---' && $lineNum > 0)
                {
                    break;
                }
                else
                {

                    $metaStr[] = $line;
                }
                
                $lineNum++;
            }
            
            $this->meta = Yaml::parse(implode("\n", $metaStr));
        }
        
        # Is there a published status defined?
        if( isset($this->meta['published']) )
        {
            $this->published = $this->meta['published'];
        }
        
        # Is there a slug in the meta?
        if( isset($this->meta['slug']) )
        {
            $this->routeInfo['filename'] = $this->meta['slug'];
        }
        else
        {
            $this->meta['slug'] = $this->getRoute();
        }
        
        # Now generate the ID:
        $slug = trim($this->meta['slug'], '/');
        $slug = (empty($slug)) ? 'index' : $slug;
        $slug = str_replace('[/]', null, $slug);
        $slug = (substr($slug, (0 - strlen($this->app->getSetting('router.filenameSuffix')))) == $this->app->getSetting('router.filenameSuffix')) ? substr($slug, 0, strlen($slug) - strlen($this->app->getSetting('router.filenameSuffix'))) : $slug;
        $slug = implode('_', explode('/', trim($slug, '/')));
        $this->meta['id'] = $this->type.'-'.$slug;
        

        # Add the route:
        $this->meta['created']   = $this->source->getCTime();
        $this->meta['modified']  = $this->source->getMTime();
    }
    
    # Get the route:
    public function getRoute()
    {
        $route = implode( null, array_values($this->routeInfo) );

        if( $route == '/[/]' )
        {
            return '/';
        }

        return str_replace('//', '/', $route);
    }

    # Get the routes to register:
    public function getRoutes()
    {
        $parts = array_values($this->routeInfo);
        $route = str_replace('//', '/', implode(null, $parts));

        if( end($parts) == '[/]' )
        {
            return [
                $route,
                rtrim($route, '[/]').'/index.html'
            ];
        }
        elseif( end($parts) == '/' )
        {
            return [
                $route,
                rtrim($route, '/').'/index.html'
            ];
        }

        return [ $route ];
    }

    # Get the filename:
    public function getFilename( bool $baseOnly = false )
    {
        return ($baseOnly) ? $this->sourceBasename : $this->sourceFilename;
    }
    
    # Get the content:
    public function getContent( bool $parseMarkdown = true, bool $parseShortcodes = true )
    {
        $lines = [ ];
        
        while( ! $this->source->eof() )
        {
            $lines[] = $this->source->fgets();
        }
        
        $content = ($parseMarkdown) ? $this->app->mdParser->text(implode($lines)) : implode($lines);
        
        if( $parseShortcodes )
        {
            $content = $this->app->parseShortcodes($content);
        }
        
        return $content;
    }
    
    # Get the meta:
    public function getMeta( string $key = null )
    {
        if( !is_null($key) )
        {
            return (array_key_exists($key, $this->meta) ? $this->meta[$key] : null);
        }

        return $this->meta;
    }

    # Get the title:
    public function getTitle()
    {
        return $this->meta['title'];
    }
    
    # Kill SPL on destory:
    function __destruct()
    {
        $this->source = null;
    }
}