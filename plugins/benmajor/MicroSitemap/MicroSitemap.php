<?php

namespace BenMajor\MicroSitemap;

class MicroSitemap extends \BenMajor\Micro\Plugin
{
	public $name    = 'Micro Sitemap plugin';
	public $version = '1.0';

	# Constructor:
	public function MicroSitemap()
	{
		$this->app->addRoute('/sitemap.xml', function($request, $response) { return $this->buildSitemap($request, $response); });
	}

	# Function to build the sitemap:
	private function buildSitemap( $request, $response, $args = [ ])
	{
		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml.= "\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

		foreach( $this->app->getAllContent() as $item )
		{
			$xml.= "\n\t".'<url>';
			#$xml.= "\n\t\t".'<loc>'.$item['url'].'</loc>';
			$xml.= "\n\t\t".'<loc>'.$item['canonical'].'</loc>';

			# Modified date is specified, use it:
			if( isset($item['modified']) && !empty($item['modified']) )
			{
				$xml.= "\n\t\t".'<lastmod>'.date('c', $item['modified']).'</lastmod>';
			}

			# No modified, set update frequency instead:
			else
			{
				$xml.= "\n\t\t".'<changefreq>weekly</changefreq>';
			}

			# Is it a high priority page?
			$xml.= "\n\t\t".'<priority>'.(in_array($item['type'], [ 'page', 'blog-post' ]) ? 1 : 0.8).'</priority>';
			$xml.= "\n\t".'</url>';
		}

		$xml.= "\n".'</urlset>';

		# Start building the XML:
		return $response->withHeader('Content-type', 'application/xml')->write($xml);
	}
}