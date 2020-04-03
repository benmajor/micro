<?php

namespace BenMajor\Micro\Controller;

use \BenMajor\Slim3Controller\Controller;
use \BenMajor\Micro\Utils;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

class Page extends Controller
{
    public function main( \BenMajor\Micro\Model\Page $page, Request $request, Response $response, $params  )
    {
        $meta = Utils::extendArray($page->getMeta(), [  ]);
        
        # Add the canonical:
        $meta['canonical_url'] = $page->getCanonicalURLPath();
        
        $data = [
            'content' => $page->getContent(),
            'meta'    => $meta
        ];
        
        $page->app->setTitle($meta['title'])
                  ->setCanonicalURL($meta['canonical_url']);
        
        return $page->app->getTwig()->render( $response, $page->getTemplate(), $data);
    }
}