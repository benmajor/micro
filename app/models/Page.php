<?php

namespace BenMajor\Micro\Model;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Page extends ContentItem
{
    function __construct( $src, \BenMajor\Micro\App $app, bool $registerRoute = true )
    {
        parent::__construct($src, $app);
        
        $this->controller = new \BenMajor\Micro\Controller\Page( $this->app->getSlim() );

        # Add the route:
        if( $this->published && $registerRoute )
        {
            $self = $this;

            $this->app->getSlim()->get( $this->getRoute(), function(Request $request, Response $response, $params) use ($self) {
                $self->app->setCurrentContentItem( $self );
                $self->controller->main($self, $request, $response, $params);
            });
        }

        # Build the canonical URL into the meta:
        $this->meta['canonical'] = $this->getCanonicalURL();

        # Is there a parent?
        $this->parent = null;

        $path = ltrim(substr($this->source->getPath(), strlen($this->app->config->get('dirs.content'))), DIRECTORY_SEPARATOR);

        # It's in a subfolder:
        if( !empty($path) )
        {
            $parts = explode(DIRECTORY_SEPARATOR, $path);

            if( count($parts) > 1 )
            {
                # If it's not index, treate the index as a parent:
                if( $this->sourceBasename != 'index.mc' )
                {
                    $parentDir = $this->app->config->get('dirs.content').DIRECTORY_SEPARATOR.implode($parts, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                }   
                else
                {
                    # Build the parent directory:
                    $parentDir = substr( rtrim($this->source->getPath(), DIRECTORY_SEPARATOR), 0, 0 - strlen(end($parts)) );
                }

                $this->parent = new self( new \SplFileObject($parentDir.'index.mc'), $this->app, false );
            }
        }
        
        # Set the type:
        $this->meta['type'] = $this->type;
        $this->meta['modified'] = filemtime($this->source->getRealPath());
    }

    # Check if the page is the homepage:
    public function isHomepage()
    {
        return isset($this->meta['homepage']) && $this->meta['homepage'];
    }

    # Get the full route (replaces optional flags):
    public function getFullRoute() 
    {
        return str_replace('[/]', '/', $this->getRoute());
    }

    # Get the canonical URL:
    public function getCanonicalURL()
    {
        return rtrim($this->app->getSiteURL(), '/').str_replace('[/]', '/', $this->getRoute());
    }

    # Get the template name:
    public function getTemplate( $baseOnly = true )
    {
        $base = $this->app->config->get('dirs.view').'/'.$this->app->config->get('site.theme').'/';
        
        # Look for .twig:
        if( file_exists($base.$this->meta['id'].'.twig') )
        {
            return ($baseOnly) ? $this->meta['id'].'.twig' : $base.$this->meta['id'].'.twig';
        }
        elseif( file_exists($base.$this->meta['id'].'.html') )
        {
            return ($baseOnly) ? $this->meta['id'].'.html' : $base.$this->meta['id'].'.html';
        }
        
        return ($baseOnly) ? basename($this->type.'.twig') : $base.$this->type.'.twig';
    }

    # Get the hierarchy:
    public function getHierarchy()
    {
        $hierarchy = [ ];
        $parent    = $this->parent;

        while( !is_null($parent) )
        {
            $hierarchy[] = $parent;

            $parent = $parent->parent;
        }

        return $hierarchy;
    }

    # Get directory for page:
    public function getDirectory()
    {
        return $this->source->getPath();
    }

    # Get the child pages:
    public function getChildren( $directory = null )
    {
        $children = [ ];

        if( $this->sourceFilename == 'index.mc' )
        {
            foreach( glob($this->getDirectory().DIRECTORY_SEPARATOR.'*.mc') as $childFile )
            {            
                $children[] = new self(new \SplFileObject($childFile), $this->app, false);
            }
        }

        return $children;
    }

}