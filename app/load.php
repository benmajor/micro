<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Symfony\Component\Yaml\Yaml;

# Load the data:
require 'app/vendor/autoload.php';
require 'app/autoload.php';

# Require the controllers and models:
foreach( glob('app/controllers/*.php') as $controller ) { require $controller; }

include 'app/functions.php';

# Create a new Slim instance:
$app  = new \BenMajor\Micro\App(
    'app/etc/config.yaml',
    (file_exists('app/etc/routes.yaml') ? Yaml::parseFile('app/etc/routes.yaml') : [ ])
);