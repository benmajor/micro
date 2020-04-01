<?php

########################
##     SETTERS        ##
########################

# Set the current PSR-7 request object:
function micro_set_request( \Psr\Http\Message\ServerRequestInterface $request )
{
    global $app;
    return $app->setRequest( $request );
}

# Set the current PSR-7 response object:
function micro_set_response( \Psr\Http\Message\ResponseInterface $response )
{
    global $app;
    return $app->setResponse( $response );
}

# Set the current source file from a route name:
function micro_set_current_source( string $route )
{
    global $app;
    return $app->setCurrentSource($route);
}

# Set the current URI:
function micro_set_current_uri( $uri )
{
    global $app;
    return $app->setCurrentURI($uri);
}

# Set the Meta title:
function micro_set_title( string $title )
{
    global $app;
    return $app->setTitle($title);
}

# Set the canonical URL:
function micro_set_canonical_url( string $url )
{
    global $app;
    return $app->setCanonicalURL( $url );
}

########################
##     GETTERS        ##
########################

# Retrieve a specific menu:
function micro_get_menu( string $name )
{
    global $app;
    return $app->getMenu( $name );
}

# Get the Slim instance:
function micro_get_slim()
{
    global $app;
    return $app->getSlim();
}

# Get the Twig instance:
function micro_get_twig()
{
    global $app;
    return $app->getTwig();
}

# Get the current source file (if it's set):
function micro_get_current_source()
{
    global $app;
    return $app->getCurrentSource();
}

# Get the current content item if it's defined:
function micro_get_current_content_item()
{
    global $app;
    return $app->getCurrentContentItem();
}

# Get the current route:
function micro_get_current_route()
{
    global $app;
    return $app->getCurrentRoute();
}

# Get the current URI:
function micro_get_current_uri()
{
    global $app;
    return $app->getCurrentURI();
}

# Get the current PSR-7 request:
function micro_get_request()
{
    global $app;
    return $app->getRequest();
}

# Get the current PSR-7 response:
function micro_get_response()
{
    global $app;
    return $app->getResponse();
}

# Get the site URL:
function micro_get_site_url()
{
    global $app;
    return $app->getSiteURL();
}

# Get the currently-enabled theme directory:
function micro_get_theme_directory()
{
    global $app;
    return $app->getThemeDirectory();
}

# Return the theme path for a specific resource:
function micro_get_theme_path_for( string $resource )
{
    global $app;
    return $app->getThemePathFor($resource);
}

# Return the content directory path:
function micro_get_content_path()
{
    global $app;
    return $app->getContentPath();
}

# Retrieve a list of currently-installed plugins:
function micro_get_plugins()
{
    global $app;
    return $app->getPlugins();
}

# Retrieve a list of OOB content:
function micro_get_content()
{
    global $app;
    return $app->getContent();
}

# Retrieve a list of all defined content items:
function micro_get_all_content()
{
    global $app;
    return $app->getAllContent();
}

# Get the currently-defined title:
function micro_get_title()
{
    global $app;
    return $app->getTitle();
}

# Get the currently-defined canonical URL:
function micro_get_canonical_url()
{
    global $app;
    return $app->getCanonicalURL();
}

# Get the site URL for a specific resource:
function micro_get_site_url_for( string $resource )
{
    global $app;
    return $app->getSiteURLFor($resource);
}

# Retrieve a list of the currently-defined Twig functions:
function micro_get_twig_functions()
{
    global $app;
    return $app->getTwigFunctions();
}

# Retrieve a list of the currently-defined shortcodes:
function micro_get_shortcodes()
{
    global $app;
    return $app->getShortcodes();
}

########################
##      UTILITIES     ##
########################

# Get a setting:
function micro_get_setting( string $selector )
{
    global $app;
    return $app->getSetting( $selector );
}

# Set a setting:
function micro_set_setting( string $selector, $value )
{
    global $app;
    return $app->setSetting($selector, $value);
}

# Save the settings:
function micro_save_settings()
{
    global $app;
    return $app->saveSettings();
}

# Add a new Twig global:
function micro_add_twig_global( string $name, $value )
{
    global $app;
    return $app->addTwigGlobal($name, $value);
}

# Add a new content item to the system (should be called from a plugin):
function micro_add_content_item( $url, bool $published = true, string $type = 'page', int $modified = null, bool $homepage = false )
{
    global $app;
    return $app->addContentItem( $url, $published, $type, $modified, $homepage );
}

# Add a new template path:
function micro_add_template_path( string $path )
{
    global $app;
    return $app->addTemplatePath( $path );
}

# Register a new menu from a file:
function micro_register_menu( string $file )
{
    global $app;
    return $app->registerMenu($file);
}

# Register a new CSS file for templates:
function micro_register_css_file( string $file )
{
    global $app;
    return $app->registerCSSFile($file);
}

# Register a new JS file for templates:
function micro_register_js_file( $file, bool $inFooter = false )
{
    global $app;
    return $app->registerJSFile($file, $inFooter);
}

# Generate a URL-friendly slug:
function micro_generate_slug( string $str )
{
    global $app;
    return $app->generateSlug( $str );
}

# Check if a specific plugin instealled:
# Note: Converts to lowercase and replaces the \ in the namespaces with underscores:
# e.g. \BenMajor\MicroBlog becomes benmajor_microblog
function micro_plugin_installed( string $name )
{
    global $app;
    return $app->pluginInstalled($name);
}

# Create a new Twig function:
function micro_create_twig_function( string $fnName, $class, string $methodName, $params )
{
    global $app;
    return $app->createTwigFunction( $fnName, $class, $methodName, $params );
}

# Register a new plugin:
function micro_register_shortcode( string $shortcode, callable $callback )
{
    global $app;
    return $app->registerShortcode($shortcode, $callback);
}

# Parse a string for currently-defined shortcodes:
function micro_parse_shortcodes( string $content )
{
    global $app;
    return $app->parseShortcodes( $content );
}

# Create a new JQL query:
function micro_jql_query( $data )
{
    global $app;
    return $app->jqlQuery( $data );
}

# Convert data to YAML:
function micro_to_yaml( $data )
{
    global $app;
    return $app->toYAML($data);
}

# Parse a YAML string:
function micro_parse_yaml( $yaml )
{
    global $app;
    return $app->parseYaml($yaml);
}

# Parse a Markdown string into HTML:
function micro_parse_markdown( string $markdown )
{
    global $app;
    return $app->parseMarkdown( $markdown );
}

# Get a parameter from the request:
function micro_get_param( string $name )
{
    global $app;
    return $app->getRequest()->getParam($name);
}

# Build a full URL using the site's settings:
function micro_url( string $slug )
{
    global $app;

    return $app->getSetting('site.url').$app->getSetting('site.dir').ltrim($slug, '/');
}