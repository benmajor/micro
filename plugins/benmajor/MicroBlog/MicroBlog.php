<?php

namespace BenMajor\MicroBlog;

use \BenMajor\MicroBlog\Controller\Blog as BlogController;

class MicroBlog extends \BenMajor\Micro\Plugin
{
	public $posts;

	protected $controller;
	
	# Constructor:
	public function MicroBlog()
	{
		$this->name 			= 'Micro Blog plugin';
		$this->version          = '1.0.0';
		$this->contentDirectory = 'blog';
		$this->urlRoute         = $this->getSetting('routeBase');
		
		$this->posts            = $this->loadData();
		$this->controller       = new BlogController( $this );
		
		# Create an array of tags:
		$this->tags = [ ];
		
		# Create an array of authors:
		$this->authors = [ ];
		
		# Create an array of categories:
		$this->categories = [ ];
		
		foreach( $this->posts as $post )
		{
			# Create the category array:
			foreach( $post['categories'] as $category )
			{
				if( ! array_key_exists($category['slug'], $this->categories) )
				{
					$this->categories[ $category['slug'] ] = $category;
				}
			}
			
			# Create the author array:
			if( ! array_key_exists($post['author_username'], $this->authors) )
			{
				$this->authors[$post['author_username']] = $post['author'];
			}
			
			# Create the tag array:
			foreach( $post['tags'] as $tag )
			{
				if( ! array_key_exists($tag['slug'], $this->tags) )
				{
					$this->tags[ $tag['slug'] ] = $tag;
				}
			}
		}
		
		$this->setupRoutes();
	}
	
	# Get the category name from a slug:
	public function getCategory( string $slug )
	{
		if( ! array_key_exists($slug, $this->categories) )
		{
			return null;
		}
		
		return $this->categories[$slug];
	}
	
	# Set up route:
	private function setupRoutes()
	{
		$base = '/'.trim($this->urlRoute, '/');
		
		# Add the routes:
		$this->app->addRoute( rtrim($base, '/').'[/]', function($request, $response) {
			return $this->controller->index();
		});
		
		# Individual posts:
		$this->app->addRoute($base.'/{post}.html', function($request, $response) {
			return $this->controller->post($atts['post']);
		});
		
		# Archive:
		$this->app->addRoute($base.'/{year:[0-9]+}[/]', function($request, $response, $atts) {
			return $this->controller->archiveYear($atts['year']);
		});
		
		$this->app->addRoute($base.'/{year:[0-9]+}/{month:[0-9]+}[/]', function($request, $response, $atts) {
			return $this->controller->archiveMonth($atts['year'], $atts['month']);
		});
		
		# Category:
		$this->app->addRoute($base.'/category/{category}[/]', function($request, $response, $atts) {
			return $this->controller->category($atts['category']);
		});
		
		# Tag route:
		$this->app->addRoute($base.'/tag/{tag}[/]', function($request, $response, $atts) {
			return $this->controller->tag($atts['tag']);
		});
		
		# Author route:
		$this->app->addRoute($base.'/author/{author}[/]', function($request, $response, $atts) {
			return $this->controller->author($atts['author']);
		});
	}
	
	# Get the blog URL:
	public function getBaseURL()
	{
		return $this->getSiteSetting('site.url').$this->getSiteSetting('site.dir').ltrim($this->urlRoute, '/');
	}
	
	# Load the data:
	private function loadData()
	{
		$posts = [ ];
		
		# Now load the posts:
        foreach( glob($this->getContentDirectory().'*.mc') as $postFile )
        {
            # Read the meta data:
            $file   = new \SplFileObject($postFile);
            $key    = pathinfo( $postFile, PATHINFO_FILENAME );
            $exists = array_key_exists($key, $posts);
            $append = 1;
            
            # Prevent duplicate keys:
            while( $exists )
            {
                $key.= '-'.$append;
                $exists = array_key_exists($key, $posts);
                $append++;
            }
            
            $post = [
                'published' => true,
                'created'   => $file->getCTime(),
                'modified'  => $file->getMTime(),
                'file'      => $postFile,
                'slug'      => $key,
                'url'       => $this->urlRoute.'/'.$key.$this->getSetting('post_suffix', '.html')
            ];
            
            $meta = [ ];
            
            # Is the first line the meta?
            if( trim($file->current()) == '---' )
            {
                # Now read the meta:
                $metaStr = [ ];
                $lineNum = 0;
                
                $file->rewind();
                
                while( ! $file->eof() )
                {
                    $line = $file->fgets();
                    
                    if( trim($line) == '---' && $lineNum > 0)
                    {
                        break;
                    }
                    elseif( $lineNum > 0 )
                    {
                        $metaStr[] = $line;
                    }
                    
                    $lineNum++;
                }
                
                $meta = $this->app->parseYaml(implode($metaStr, "\n"));
                
                if( isset($meta['author']) )
                {
					$post['author_username'] = $meta['author']['username'];
                    $post['author']['url']   = $this->getBaseURL().'/author/'.$meta['author']['username'].'/';
                }
            }

            
            $posts[$key] = array_merge($post, $meta);

            # Rename raw tags to tagList:
            $posts[$key]['tagList'] = $posts[$key]['tags'];
            unset($posts[$key]['tags']);

            # Add tags with full data:
            foreach( $posts[$key]['tagList'] as $tag )
            {
                $posts[$key]['tags'][] = [
                    'name' => $tag,
                    'url'  => $this->getBaseURL().'/tag/'.$tag.'/',
					'slug' => $tag
                ];
            }

            # Same process for categories:
            $posts[$key]['categoryList'] = $posts[$key]['category'];
			$posts[$key]['categorySlugList'] = [ ];

            # Now add category data:
            foreach( $posts[$key]['categoryList'] as $cat )
            {
                $posts[$key]['categories'][] = [
                    'name' => $cat,
                    'url'  => $this->getBaseURL().'/category/'.$this->app->generateSlug($cat).'/',
					'slug' => $this->app->generateSlug($cat)
                ];
				
				$posts[$key]['categorySlugList'][] = $this->app->generateSlug($cat);
            }
			
			# Remove erroneous category list:
			unset($posts[$key]['category']);
			unset($posts[$key]['categoryList']);
            
            # Null the SPL object:
            $file = null;
        }
		
		return $posts;
	}
}