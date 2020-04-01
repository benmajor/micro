<?php

namespace BenMajor\DemoPlugin;

class DemoPlugin extends \BenMajor\Micro\Plugin
{
	public $name    = 'Demo Plugin';
	public $version = '1.0';

	# Constructor:
	public function DemoPlugin()
	{
		$this->app->addTemplatePath('plugins/benmajor/DemoPlugin/views');

		$this->app->addRoute('/plugin-demo.html', function($request, $response) {
			$this->app->setTitle('Demo Plugin');
			$this->app->setCanonicalURL( $this->app->getSiteURLFor('plugin-demo.html') );
			
			return $this->app->getTwig()->render( $response, 'demo-plugin.twig' );
		});
	}
}