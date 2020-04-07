<?php

namespace BenMajor\Micro\Model;

class TwigExtensions extends \Twig_Extension
{
	protected $app;
	protected $container;

	function __construct( \BenMajor\Micro\App $app, $container )
	{
		$this->app       = $app;
		$this->container = $container;
	}

	public function getFunctions()
	{
		$this->app->createTwigFunction('breadcrumbs',   $this, 'breadcrumbs', 	[ 'is_safe' => ['html'] ]);
		$this->app->createTwigFunction('menu',          $this, 'menu', 			[ 'is_safe' => ['html'] ]);
		$this->app->createTwigFunction('meta_title',    $this, 'meta_title',    [ 'is_safe' => ['html'] ]);
		$this->app->createTwigFunction('canonical_url', $this, 'canonical_url', [ 'is_safe' => ['html'] ]);
		$this->app->createTwigFunction('markdown',      $this, 'markdown',      [ 'is_safe' => ['html'] ]);
		
		$this->app->createTwigFunction('micro_head', 	$this, 'micro_head', 	[ 'is_safe' => ['html'] ]);
		$this->app->createTwigFunction('micro_foot',	$this, 'micro_foot', 	[ 'is_safe' => ['html'] ]);
		
		return array_values($this->app->getTwigFunctions());
	}

	# Function to generate the price:
	public function menu( $menuName, $tag = 'ul', $atts = [ ] )
	{
		if( isset($this->app->menus[$menuName]) )
		{
			return $this->app->menus[$menuName]->getHTML($atts, $tag);
		}
		else
		{
			return '';
		}
	}

	# Function to generate a breadcrumbs:
	public function breadcrumbs( $theCrumbs = [ ], $atts = [ ], $addSchema = true )
	{
		$attributes = [ ];

        if( empty($atts) || !isset($atts['class']) )
        {
            $attributes[] = 'class="breadcrumbs"';
        }
        else
        {
            foreach( $atts as $name => $val )
            {
            	if( is_array($val) )
            	{
            		if( $name == 'class' )
	                {
	                    $val[] = 'breadcrumbs';
	                }

	                $attributes[] = ' '.$name.'="'.implode($val, ' ').'"';
            	}
            	else
            	{
	            	if( $name == 'class' )
	                {
	                    $val.= ' breadcrumbs';
	                }

	                $attributes[] = ' '.$name.'="'.$val.'"';
	            }
            }
        }

        # Are we adding Rich Snippets?
        if( $addSchema )
        {
        	$attributes[] = 'itemscope';
        	$attributes[] = 'itemtype="https://schema.org/BreadcrumbList"';
        }

        # Build the crumbs:
        $crumbs = [ ];
	
		if( empty($theCrumbs) )
		{
			# Get the hierarchy of the content item:
			if( !is_null($this->app->getCurrentContentItem()) )
			{
				# Add the current page:
				$crumbs[] = [
					'name'    => $this->app->getCurrentContentItem()->getTitle(),
					'url'     => $this->app->getCurrentContentItem()->getCanonicalURL(),
					'current' => true
				];
				
				foreach( $this->app->getCurrentContentItem()->getHierarchy() as $crumb )
				{
					$crumbs[] = [
						'name' 	  => $crumb->getTitle(),
						'url' 	  => $crumb->getCanonicalURL(),
						'current' => false
					];
				}   
			}
		}
		
  		# No crumbs, don't return anything:
  		if( ! count($crumbs) )
  		{
  			return '';
  		}

		if( empty($theCrumbs) )
		{
			$crumbs[] = [ 
				'name' 	  => 'Home',
				'url' 	  => $this->app->config->get('site.url'),
				'current' => false
			];
		}
		else
		{
			$crumbs = $theCrumbs;
		}

        # Build the HTML string:
        $html = "\n".'<ol '.implode(' ', array_unique($attributes)).'>';

        # We need to reverse the breadcrumbs:
        $pos = 1;
		$crumbs = (empty($theCrumbs) ? array_reverse($crumbs) : $theCrumbs);
				   
        foreach( $crumbs as $crumb )
        {
        	$html.= "\n\t".'<li class="breadcrumb-item breadcrumb-'.$pos.($pos == 1 ? ' first' : '').($pos == count($crumbs) ? ' last' : '').'"';

        	if( $addSchema )
        	{
        		$html.= ' itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"';
        	}

        	$html.= '>';

            if( ! $crumb['current'] )
            {
                $html.= '<a href="'.$crumb['url'].'"';

                if( $addSchema )
                {
                    $html.= ' itemprop="item"';
                }

                $html.= '>';
            }

        	$html.= '<span';

        	if( $addSchema )
        	{
        		$html.= ' itemprop="name"';
        	}

        	$html.= '>'.$crumb['name'].'</span>';

        	$html.= '<meta itemprop="position" content="'.$pos.'" />';
        	
            if( ! $crumb['current'] )
            {
                $html.= '</a>';
            }

        	$html.= "\n\t".'</li>';

        	$pos++;
        }

        $html.= "\n".'</ol>';

        return $html;
	}

	# Function to output the meta title:
	public function meta_title()
	{
		$title = $this->app->getTitle();
		
		if( is_null($title) )
		{
			return $this->app->config->get('site.title');
		}
		
		return $title.$this->app->config->get('site.titleSeparator').$this->app->config->get('site.title');
	}
	
	# Set the canonical tag:
	public function canonical_url()
	{
		if( ! is_null($this->app->getCanonicalURL()) )
		{
			return '<link rel="canonical" href="'.$this->app->getCanonicalURL().'" />';
		}
		
		return '';
	}
	
	# Parse some markdown content:
	public function markdown( string $markdown )
	{
		return $this->app->parseMarkdown( $markdown );
	}
	
	# Micro <head>:
	public function micro_head()
	{
		$html = '';
		
		if( ! is_null($this->app->getCanonicalURL()) )
		{
			$html.= "\n\t\t".'<link rel="canonical" href="'.$this->app->getCanonicalURL().'" />';
		}
		
		# There are some head JS files, add a new line:
		if( ! empty($this->app->headJSFiles) )
		{
			$html.= "\n";
		}
		
		# Add the head JS:
		foreach( $this->app->headJSFiles as $file )
		{
			$html.= "n\t\t".'<script type="text/javascript" src="'.$file.'"></script>';
		}
		
		# There are some head CSS files, add a new line:
		if( ! empty($this->app->headCSSFiles) )
		{
			$html.= "\n";
		}
		
		# Loop over and add the CSS files:
		foreach( $this->app->headCSSFiles as $file )
		{
			$html.= "\n\t\t".'<link rel="stylesheet" href="'.$file.'" />';
		}
		
		$html.= "\n";
		
		return $html;
	}
	
	# Code to be injected immidately before </body>:
	public function micro_foot()
	{
		$html = '';
		
		# Add the head JS:
		foreach( $this->app->footJSFiles as $file )
		{
			$html.= "n\t\t".'<script type="text/javascript" src="'.$file.'"></script>';
		}
		
		return $html;
	}

}