<?php

use Symfony\Component\Yaml\Yaml;

#################################################
#                                               #
#               MICRO INSTALLER                 #
#                                               #
#################################################
#                                               #
#  This is the main Micro installer. If the     #
#  folder exists, it will always run, so the    #
#  _install directory MUST be removed after     #
#  the installer has been executed.             #
#                                               #
#     DO NOT MODIFY THIS FILE IN ANY WAY!       #
#                                               #
#################################################

require '../app/vendor/autoload.php';

define('MICRO_MIN_PHP_VERSION', 7.0);
define('MICRO_DIR_CONTENT',     '../content/');
define('MICRO_DIR_THEMES',      '../themes/' );
define('MICRO_DIR_CONFIG',      '../app/etc/');

# Process and go to Step 2:
if( isset($_POST['goto-2']) )
{
    micro_render('install');
}

# Are we installing?
elseif( isset($_POST['install']) )
{
    $siteName      = trim($_POST['site-name']);
    $siteDomain    = trim($_POST['site-domain']);
    $siteDirectory = trim($_POST['site-directory']);
    $siteTheme     = trim($_POST['site-theme']);
    
    if( empty($siteName) )
    {
        $tpl['alert'] = [
            'cls' => 'danger',
            'txt' => 'Please specify the name of your Micro website.'
        ];
    }
    
    if( empty($siteDomain) )
    {
        $tpl['alert'] = [
            'cls' => 'danger',
            'txt' => 'Please specify the URL of your Micro website.'
        ];
    }
    
    if( empty($siteDirectory) )
    {
        $tpl['alert'] = [
            'cls' => 'danger',
            'txt' => 'Please specify the installation directory for your Micro website.'
        ];
    }
    
    if( empty($siteTheme) )
    {
        $tpl['alert'] = [
            'cls' => 'danger',
            'txt' => 'Please choose a theme for your Micro website.'
        ];
    }
    
    # There has been an error:
    if( isset($tpl['alert']) )
    {
        micro_render('install');
    }
    
    # No error, let's try to handle the installation:
    else
    {
        $config = fopen('../app/etc/config.yaml', 'w+');
        
        $yaml = [
            'site' => [
                'url'            => $siteDomain,
                'dir'            => $siteDirectory,
                'title'          => $siteName,
                'titleSeparator' => ' | ',
                'theme'          => $siteTheme,
                'cache'          => false,
                'devMode'        => false,
                'timezone'       => date_default_timezone_get(),
                'locale'         => locale_get_default(),
                'meta'           => [
                    'title'      => null,
                    'description'=> null,
                    'keywords'   => [ ]
                ],
                'analytics'      => [
                    'utm'        => null
                ]
            ],
            
            'views' => [
                'page' => 'page.twig'
            ],
            
            'twig' => [
                'globals' => [
                    'dateFormat' => 'Y-m-d H:i:s'
                ]
            ],
            
            'router' => [
                'filenameSuffix' => '.html'
            ],
            
            'ftp' => [
                'hostname' => null,
                'username' => null,
                'password' => null,
                'portnum'  => null
            ],
            
            'dirs' => [
                'view'    => 'themes',
                'content' => 'content',
                'plugins' => 'plugins'
            ]
        ];
        
        fwrite($config, Yaml::dump($yaml));
        fclose($config);
        
        micro_render('done');
    }
}
elseif( isset($_POST['done']) )
{
    header('Location: ../?installed=1');
    exit();
}

# Main page:
else
{
    if( micro_is_installed() )
    {
        micro_render('installed');
        exit(1);
    }
    
    micro_render('welcome');
}

# Functions:
function micro_is_installed()
{
    return (file_exists('../app/etc/config.yaml'));
}

function micro_render( $view )
{
    if( ! file_exists('views/'.$view.'.php') )
    {
        throw new Exception('Installer view file '.$view.' does not exist!');
    }
    
    include 'views/'.$view.'.php';
}

function micro_min_php_verion()
{
    return phpversion() >= MICRO_MIN_PHP_VERSION;
}

function micro_json_enabled()
{
    return function_exists('json_encode') && function_exists('json_decode');
}

function micro_mbstring_enabled()
{
    return extension_loaded('mbstring');
}

function micro_content_directory_exists()
{
    return (file_exists(MICRO_DIR_CONTENT) && is_dir(MICRO_DIR_CONTENT));
}

function micro_content_directory_is_writable()
{
    return is_writable(MICRO_DIR_CONTENT);
}

function micro_etc_directory_exists()
{
    return (file_exists(MICRO_DIR_CONFIG) && is_dir(MICRO_DIR_CONFIG));
}

function micro_etc_directory_is_writable()
{
    return is_writable(MICRO_DIR_CONFIG);
}

function micro_theme_installed()
{
    return count(micro_get_themes()) > 0;
}

function micro_get_domain()
{
    $protocol = strpos(strtolower($_SERVER['SERVER_PROTOCOL']),'https') === FALSE ? 'http' : 'https';
    
    return $protocol.'://'.$_SERVER['HTTP_HOST'];
}

function micro_get_directory()
{
    return str_replace(
        [
            '_install/index.php',
            '_install'
        ], '', dirname($_SERVER['PHP_SELF'])
    );
}

function micro_get_themes()
{
    $themes = [ ];
    
    foreach( glob(MICRO_DIR_THEMES.'/*', GLOB_ONLYDIR) as $theme )
    {
        if( file_exists($theme.DIRECTORY_SEPARATOR.'page.twig') )
        {
            $themes[] = basename($theme);
        }
    }
    
    return $themes;
}