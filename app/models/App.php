<?php

namespace BenMajor\Micro;

use \Symfony\Component\Yaml\Yaml;
use \Ausi\SlugGenerator\SlugGenerator;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \BenMajor\JQL\JQL;

use \BenMajor\Micro\Exception\RouteException;
use \BenMajor\Micro\Exception\ShortcodeException;
use \BenMajor\Micro\Exception\TwigException;

class App
{
    public $config;
    public $mdParser;
    public $slugGenerator;
    public $menus;
    public $currentSource;
    public $currentContentItem;
    public $currentURI = null;
    
    # Properties useful for Twig:
    public $metaTitle    = null;
    public $canonicalURL = null;
    public $headCSSFiles = [ ];
    public $headJSFiles  = [ ];
    public $footJSFiles  = [ ];
    
    protected $slim;
    protected $twig;
    protected $router;
    
    protected $plugins       = [ ];
    protected $twigFunctions = [ ];
    protected $shortcodes    = [ ];
    
    private $controllers;
    private $request;
    private $response;

    protected $version = '1.0.0';
    
    function __construct( $config, $routes = [ ] )
    {
        $this->config = new Model\ConfigList($config);
        
        # Grab the additional config settings from the PHP files
        # that exist in the etc drive:
        $this->setupPHPConfigFiles();
        
        # Set up a slim instance:
        $this->slim     = new \Slim\App([ 
            'settings' => [ 
                'displayErrorDetails' => $this->config->get('site.devMode'),
                'determineRouteBeforeAppMiddleware' => false
            ] 
        ]);

        $this->routes        = json_decode(json_encode($routes), false);
        $this->mdParser      = new \Parsedown();
        $this->slugGenerator = new SlugGenerator();
        $this->router        = new \BenMajor\Micro\Model\Router([], $this);

        # I18n:
        date_default_timezone_set( $this->config->get('site.timezone') );
        setlocale(LC_TIME, $this->config->get('site.locale'));

        # Internal setup:
        $this->setupTwig();
        $this->loadContent();
        $this->loadMenus();
        $this->registerTwigGlobals();
        $this->setupMiddleware();
        $this->setupPlugins();

        # Add the Twig globals:
        foreach( $this->config->get('twig.globals') as $name => $value )
        {
            $this->addTwigGlobal($name, $value);
        }
    }
    
    # Register a new route:
    public function addRoute( $route, $callback, string $method = 'get' )
    {
        # Is it a protected route?
        if( !is_null($this->config->get('site.routes')) && in_array($route, json_decode(json_encode($this->config->get('site.routes')), true)) )
        {
            throw new RouteException($route.' is a protected route and cannot be defined. To use it, change the system-defined routes in config.yaml.');
        }

        $this->slim->{ $method }($route, function($request, $response, $args) use ($callback) {
            return call_user_func($callback, $request, $response, $args);
        });
    }
    
    # Get a menu by name:
    public function getMenu( string $name )
    {
        return (isset($this->menus[$name])) ? $this->menus[$name] : null;
    }
    
    # Run the app:
    public function run()
    {
        # If there's a functions.php file in the theme, include it:
        if( file_exists($this->getThemePathFor('functions.php')) )
        {
            include $this->getThemePathFor('functions.php');
        }
        
        $this->slim->run();
    }

    # Adds a global value to Twig:
    public function addTwigGlobal( string $name, $value )
    {
        return $this->twig->getEnvironment()->addGlobal( $name, $value );
    }
    
    # Get the Slim instance:
    public function getSlim()
    {
        return $this->slim;
    }
    
    # Get the Twig instance:
    public function getTwig()
    {
        return $this->twig;
    }

    # Sets the current content item source:
    public function setCurrentSource( string $item )
    {
        $source = substr(
            Utils::removeDomainFromURL($item, $this->config->get('site.url')),
            strlen($this->config->get('site.dir')) - 1
        );

        $this->currentSource = null;

        foreach( $this->config->_contentMap as $route => $content )
        {
            if( $route == $source )
            {
                $this->currentSource = $content;
            }
        }

        # Return object to preserve method-chaining:
        return $this;
    }

    # Get the current content item source:
    public function getCurrentSource()
    {
        return $this->currentSource;
    }

    # Set the current content item:
    public function setCurrentContentItem( $contentItem )
    {
        $this->currentContentItem = $contentItem;
    }

    # Get the current content item:
    public function getCurrentContentItem()
    {
        return $this->currentContentItem;
    }

    # Get the current URL:
    public function getCurrentRoute()
    {
        return substr(
            $_SERVER['REQUEST_URI'],
            (strlen($this->config->get('site.dir')) - 1)
        );
    }
    
    # Set the current URI:
    public function setCurrentURI( $uri )
    {
        $this->currentURI = $uri;
    }
    
    # Get the current URI:
    public function getCurrentURI( bool $includeHost = true )
    {
        $host = $this->currentURI->getScheme().'://'.$this->currentURI->getHost().$this->currentURI->getBasePath();
        
        return ($includeHost) ? $host.'/'.ltrim($this->currentURI->getPath(), '/') : '/'.ltrim($this->currentURI->getPath(), '/');
    }
    
    # Set the current request:
    public function setRequest( Request $request )
    {
        $this->request = $request;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Get the current PSR-7 request object:
    public function getRequest()
    {
        return $this->request;
    }
    
    # Set the current response:
    public function setResponse( Response $response )
    {
        $this->response = $response;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Get the current PSR-7 response object:
    public function getResponse()
    {
        return $this->response;
    }

    # Get the site URL:
    public function getSiteURL()
    {
        return $this->config->get('site.url').$this->config->get('site.dir');
    }

    # Get the theme path:
    public function getThemeDirectory()
    {
        return $this->config->get('dirs.view').DIRECTORY_SEPARATOR.$this->config->get('site.theme').DIRECTORY_SEPARATOR;
    }
    
    # Return the path to a file in the currently intalled theme directory (optionally relative or absolute)
    public function getThemePathFor( $file, $absolute = false )
    {
        $url = '';
        
        if( $absolute )
        {
            $url.= $this->config->get('site.url').$this->config->get('site.dir');
        }
        
        $url.= $this->getThemeDirectory();
        $url.= $file;
        
        return $url;
    }
    
    # Get the content path:
    public function getContentPath()
    {
        return $this->config->get('dirs.content').DIRECTORY_SEPARATOR;
    }

    # Get the installed plugins:
    public function getPlugins()
    {
        return $this->plugins;
    }

    # Get page content:
    public function getContent()
    {
        return $this->content;
    }

    # Get all content including blog:
    public function getAllContent()
    {
        return $this->content;
    }
    
    # Add a new content item:
    public function addContentItem( $url, bool $published = true, string $type = 'page', int $modified = null, bool $homepage = false )
    {
        $contentItem = [
            'url'       => $url,
            'published' => $published,
            'type'      => $type,
            'homepage'  => $homepage
        ];
        
        if( ! is_null($modified) )
        {
            $contentItem['modified'] = $modified;
        }
        
        # Return object to preseve method-chaining:
        return $this;
    }

    # Add a template path:
    public function addTemplatePath( string $path )
    {
        $loader = $this->getTwig()->getEnvironment()->getLoader();
        $loader->addPath( ltrim($path, '/') );
    }
    
    # Parse Markdown:
    public function parseMarkdown( string $markdown )
    {
        return $this->mdParser->text( $markdown );
    }
    
    # Parse a YAML file:
    public function parseYaml( $yaml, $assoc = true )
    {
        return ($assoc) ? Yaml::parse($yaml) : json_decode(json_encode(Yaml::parse($yaml)));
    }
    
    # Convert to YAML:
    public function toYAML( $content )
    {
        return Yaml::dump($content);
    }
    
    # Wrapper for JQL constructor:
    public function jqlQuery( $data )
    {
        return new JQL($data);
    }
    
    # Generate a URL slug:
    public function generateSlug( string $toConvert )
    {
        return $this->slugGenerator->generate( $toConvert );
    }
    
    # Set the current title:
    public function setTitle( string $title )
    {
        $this->metaTitle = $title;
        
        # Return object to preseve method chaining:
        return $this;
    }
    
    # Get the current title:
    public function getTitle()
    {
        return $this->metaTitle;
    }
    
    # Set the canonical URL:
    public function setCanonicalURL( string $canonical )
    {
        if( ! substr($canonical, 0, strlen($this->config->get('site.url').$this->config->get('site.dir'))) != $this->config->get('site.url').$this->config->get('site.dir') )
        {
            $canonical = $this->config->get('site.url').$this->config->get('site.dir').ltrim($canonical, '/');
        }
        
        $this->canonicalURL = $canonical;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Get the canonical URL:
    public function getCanonicalURL()
    {
        return $this->canonicalURL;
    }
    
    # Register a new menu:
    public function registerMenu( string $file )
    {
        $menu = new Model\Menu($file, $this);
        $this->menus[ $menu->getName() ] = $menu;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Enqueue a CSS file for use in Twig:
    public function registerCSSFile( $file )
    {
        $this->headCSSFiles[] = $file;
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Enqueue a JS file for use in Twig:
    public function registerJSFile( $file, $inFooter = false )
    {
        if( $inFooter )
        {
            $this->footJSFiles[] = $file;
        }
        else
        {
            $this->headJSFiles[] = $file;
        }
        
        # Return object to preserve method-chaining:
        return $this;
    }
    
    # Checks if a specific plugin is installed:
    # Note: Converts to lowercase and replaces the \ in the namespaces with underscores:
    # e.g. \BenMajor\MicroBlog becomes benmajor_microblog
    public function pluginInstalled( string $pluginName )
    {
        return array_key_exists( $pluginName, $this->plugins );
    }
    
    # Retrieves an absolute URL for a specific resource (handy for use in plugins when setting the canonical URL, etc):
    public function getSiteURLFor( string $resource )
    {
        return $this->config->get('site.url').$this->config->get('site.dir').ltrim($resource, '/');
    }
    
    # Create a new Twig function:
    public function createTwigFunction( string $functionName, $class, string $methodName, $params )
    {
        # Is there already a function added?
        if( array_key_exists($functionName, $this->twigFunctions) )
        {
            throw new TwigException('Twig function: '.$functionName.' already exists. Please rename your function.');
        }
        
        $this->twigFunctions[$functionName] = new \Twig_simpleFunction($functionName, [ $class, $methodName ], $params);
        
        return $this->twigFunctions[$functionName];
    }
    
    # Get the the current Twig functions:
    public function getTwigFunctions()
    {
        return $this->twigFunctions;
    }
    
    # Register a new shortcode:
    public function registerShortcode( $shortcode, $callback )
    {
        # Check if a shortcode has already been defined:
        if( array_key_exists($shortcode, $this->getShortcodes()) )
        {
            throw new ShortcodeException('A shortcode is already defined with the code '.$shortcode.'.');
        }
        
        $this->shortcodes[$shortcode] = new Model\Shortcode($shortcode, $callback, $this);
        
        return $this;
    }
    
    # Get the shortcodes:
    public function getShortcodes()
    {
        return $this->shortcodes;
    }
    
    # Parse shortcodes:
    public function parseShortcodes( string $content )
    {
        $parsed = $content;
        
        foreach( $this->shortcodes as $shortcode )
        {
            $parsed = $shortcode->parseText( $parsed );
        }
        
        return $parsed;
    }
    
    # Get a setting:
    public function getSetting( string $path )
    {
        return $this->config->get($path);
    }
    
    # Set a setting:
    public function setSetting( string $path, $value )
    {
        return $this->config->set($path, $val);
    }
    
    # Save the settings file:
    public function saveSettings()
    {
        return $this->config->saveFile();
    }
    
    # Render a specific view:
    public function render( string $template, $data = [ ] )
    {
        return $this->getTwig()->render( $this->getResponse(), $template, $data);
    }

    # Get the current Micro version:
    public function getVersion()
    {
        return $this->version;
    }

    # Load the menus:
    private function loadMenus()
    {
        $this->menus = [ ];
        
        foreach( glob($this->config->get('dirs.content').'/_*.{yaml,yml}', GLOB_BRACE) as $menuSource )
        {
            $this->registerMenu($menuSource);
        }
    }
    
    # Load the content:
    private function loadContent()
    {
        $this->content = [ ];
        
        foreach( new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->config->get('dirs.content'))) as $filename )
        {
            $isUnderscored = (substr($filename, strlen($this->config->get('dirs.content').DIRECTORY_SEPARATOR))[0] == '_');
            
            if( !$isUnderscored && !is_dir($filename) )
            {
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $basename  = basename($filename);
                
                # It's not an underscored file, and is an allowed type, so load:
                if( $extension == 'mc' )
                {
                    $item = new Model\Page( new \SplFileObject($filename), $this);
                    
                    # Assign the item to the content array:
                    $this->content[] = [
                        'url'       => $item->getRoute(),
                        'published' => $item->published,
                        'type'      => 'page',
                        'homepage'  => $item->isHomepage(),
                        'modified'  => $item->meta['modified'],
                        'canonical' => $item->getCanonicalURL()
                    ];
                    
                    if( $item->published )
                    {
                        $this->config->_contentMap[ $item->getFullRoute() ] = (string) $filename;
                    }
                }
            }
        }
    }

    # Register the view:
    private function setupTwig()
    {
        $self = $this;
        
        // Register the view:
        $container = $this->slim->getContainer();
        
        // Register component on container
        $container['view'] = function ($container) use ($self) {
            
            $view = new \Slim\Views\Twig($self->config->get('dirs.view').DIRECTORY_SEPARATOR.$self->config->get('site.theme'), [
                'cache' => (!$self->config->get('site.devMode') && $self->config->get('site.cache')) ? 'cache' : false
            ]);
        
            // Instantiate and add Slim specific extension
            $router = $container->get('router');
            $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));

            $view->addExtension(new \Slim\Views\TwigExtension($router, $uri));
            $view->addExtension(new \BenMajor\Micro\Model\TwigExtensions($self, $container));

            return $view;
        };
        
        $this->twig = $container['view'];
        
        return $container['view'];
    }

    # Register some Twig globals:
    private function registerTwigGlobals()
    {
        $files = array_merge(
            glob( $this->getThemeDirectory().'*.yaml' ),
            glob( $this->getContentPath().'*.yaml')
        );

        # Get the *.yaml files (either from content or from the theme):
        foreach( $files as $yaml )
        {
            if( substr(basename($yaml), 0, 1) != '_' )
            {
                $this->addTwigGlobal(
                    Utils::removeExtension($yaml),
                    Yaml::parseFile($yaml)
                );
            }
        }

        # Now add additional useful vars:
        $this->addTwigGlobal('theme_dir', $this->config->get('site.url').$this->config->get('site.dir').$this->config->get('dirs.view').'/'.$this->config->get('site.theme').'/');
        $this->addTwigGlobal('base_url', $this->config->get('site.url').$this->config->get('site.dir'));
    }

    # Set up the Slim middleware:
    private function setupMiddleware()
    {
        $this->getSlim()->add(new Model\Middleware( $this ));
    }
    
    # Set up installed plugins:
    private function setupPlugins( $path = 'plugins' )
    {
        foreach( glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR) as $dir )
        {
            if( is_dir($dir) )
            {
                $dirs  = explode(DIRECTORY_SEPARATOR, trim(substr($dir, 8), DIRECTORY_SEPARATOR));

                $vendor = $dirs[0];
                $class  = $dirs[1];
                
                # Create a new object (will trigger autoloader):
                $constructor = $vendor.'\\'.$class.'\\'.$class;
                $plugin      = new $constructor( $this );

                $this->plugins[ strtolower($vendor.'_'.$class) ] = $plugin;

                # Now call the plugin:
                $plugin->$class();
                
                # Is there a content folder that we need to create?
                if( ! is_null($plugin->getContentDirectory() ) )
                {
                    $dirName = $this->config->get('dirs.content').DIRECTORY_SEPARATOR.'_'.$plugin->getContentDirectory();
                    
                    # Doesn't exist, so create it:
                    if( ! file_exists($dirName) )
                    {
                        @mkdir($dirName, 644);
                    }
                }
            }
        }
    }
    
    # Grab the PHP files out of the config directory:
    private function setupPHPConfigFiles()
    {
        foreach( glob('app/etc/*.php') as $file )
        {
             $key = str_replace('.php', null, basename($file));
            $data = include $file;
            
            $this->config->add($key, $data);
        }
    }
}