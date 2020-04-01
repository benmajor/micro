<?php

namespace BenMajor\Micro\Model;

use \BenMajor\Micro\Utils;
use \Symfony\Component\Yaml\Yaml;

class Menu
{
    protected $name;
    protected $items;
    protected $source;
    protected $app;

    private $currentURL;
    
    function __construct( string $source, \BenMajor\Micro\App $app )
    {
        $this->app    = $app;
        $this->source = $source;
        
        # Parse the name:
        $this->name = substr( Utils::removeExtension($source), 1 );

        # Parse the file and get the items:
        $this->items = Yaml::parseFile( $this->source );

        $this->currentURL = substr(Utils::getURL(), (strlen($this->app->config->get('site.url')) - 1));
    }
    
    # Get the name:
    public function getName()
    {
        return $this->name;
    }
    
    # Get the itiems:
    public function getItems()
    {
        return $this->items;
    }
    
    # Get the menu HTML:
    public function getHTML( array $atts = [ ], string $tag = 'ul' )
    {
        $attributes = [];

        if( empty($atts) || !isset($atts['class']) )
        {
            $attributes[] = ' class="menu-'.$this->name.'"';
        }
        else
        {
            foreach( $atts as $name => $val )
            {
                if( is_array($val) )
                {
                    if( $name == 'class' )
                    {
                        $val[] = 'menu-'.$this->name;
                    }

                    $attributes.= ' '.$name.'="'.implode($val, ' ').'"';
                }
                else
                {
                    if( $name == 'class' )
                    {
                        $val.= ' menu-'.$this->name;
                    }

                    $attributes[]= ' '.$name.'="'.$val.'"';
                }
            }
        }

        $attributes = implode(array_unique($attributes), ' ');

        $html = '<'.$tag.$attributes.'>';

        foreach( $this->items as $item )
        {
            $html.= $this->getMenuItem($item, $tag);
        }

        $html.= "\n".'</'.$tag.'>';

        return $html;
    }

    # Retrieve a menu item:
    private function getMenuItem( $item, $tag, $level = 1, $itemClass = [ ] )
    {
        $html = '';

        $title  = (isset($item['title']))  ? $item['title'] : $item['text'];
        $target = (isset($item['target'])) ? ' target="'.$item['target'].'"' : '';
            
        $class = [
            'menu-item',
            'level-'.$level
        ];
        
        if( $this->app->getCurrentRoute() == $item['url'] || $this->app->getCurrentURI(false) == $item['url'] )
        {
            $class[] = 'active-menu-item';
        }

        # Check if the URL is root:
        elseif( $item['url'] == '/' )
        {

        }

        # Does it contain the URL?
        #elseif( substr($this->app->getCurrentRoute(), 0, strlen($item['url'])) == $item['url'] || substr($this->app->getCurrentURI(false), 0, strlen($item['url'])) == $item['url'] )
        elseif( substr($this->app->getCurrentURI(false), 0, strlen($item['url'])) == $item['url'] )
        {
            $class[] = 'active-parent-item';
        }

        $html.= "\n\t".str_repeat("\t", $level).'<li';

        if( isset($item['class']) )
        {
            if( is_array($item['class'])) 
            {
                $class = array_merge($item['class'], $class);
            }
            else
            {
                $class[] = $item['class'];
            }
        }
        
        # Does it have children?
        if( isset($item['children']) && !empty($item['children']) )
        {
            $class[] = 'has-child';
        }

        # Add the classes for the item:
        $class = array_unique(
            array_merge($class, $itemClass)
        );

        $childHTML = (isset($item['children'])) ? $this->getChildHTML($tag, $item['children'], $level) : '';

        # Add active parent class to the item:
        if( strstr($childHTML, 'active-parent-menu') )
        {
            $class[] = 'active-parent-item';
        }

        $html.= ' class="'.implode($class, ' ').'">';

        $html.= '<a href="'.rtrim($this->app->config->get('site.url').$this->app->config->get('site.dir'), '/').'/'.ltrim($item['url'], '/').'" title="'.$title.'"'.$target.'">';
        $html.= $item['text'];
        $html.= '</a>';
        $html.= $childHTML;
        $html.= "\n\t".str_repeat("\t", $level).'</li>';

        return $html;
    }

    # Get the child HTML:
    private function getChildHTML( $tag = 'ul', $items = [ ], $level = 2 )
    {   
        $menuClass = [
            'menu-child',
            'level-'.$level
        ];

        $itemHTML = '';

        $curRoute = $this->app->getCurrentRoute();

        foreach( $items as $i => $item )
        {
            $classes = [ ];

            # It's the first item:
            if( ! $i )
            {
                $classes[] = 'first';
            }

            # It's the last item:
            if( $i == count($items) - 1)
            {
                $classes[] = 'last';
            }

            if( $curRoute != '/' && substr($item['url'], 0, strlen($curRoute)) == $item['url'] )
            {
                $menuClass[] = 'active-parent-menu';
            }

            $itemHTML.= $this->getMenuItem($item, $tag, $level + 1, $classes);
        }

        $html = "\n".str_repeat("\t", $level).'<'.$tag.' class="'.implode($menuClass, ' ').'">';
        $html.= $itemHTML;
        $html.= '</'.$tag.'>'."\n";

        return $html;
    }
}