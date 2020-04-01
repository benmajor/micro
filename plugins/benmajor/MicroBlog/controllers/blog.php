<?php

namespace BenMajor\MicroBlog\Controller;

class Blog
{
    private $posts;
    private $base;
    private $blogInstance;
    private $jql;
    
    function __construct( $blogInstance )
    {
        $this->blogInstance = $blogInstance;
        $this->base  = $blogInstance->getSetting('routeBase');
        $this->posts = $blogInstance->posts;
        $this->jql   = $this->blogInstance->getApp()->jqlQuery($this->posts);
    }
    
    # Main Blog index:
    public function index()
    {
        \micro_add_template_path('/plugins/benmajor/microblog/views/');

        $data = [
            'meta' => [
                'title'        => \micro_set_title('Blog'),
                'description'  => '',
                'canonicalURL' => \micro_set_canonical_url( trim($this->base, '/').'/')
            ]
        ];

        $count    = count($this->posts);
        $perPage  = $this->blogInstance->getSetting('postsPerPage');
        $numPages = ceil( $count / $perPage );
        $page     = (\micro_get_param('page') == null) ? 1 : \micro_get_param('page');

        # Add the pagination object to twig:
        $data['pagination'] = [
            'url'         => \micro_url($this->base.'/?page={page}'),
            'currentPage' => $page,
            'perPage'     => $perPage,
            'from'        => (($page - 1) * $perPage) + 1,
            'to'          => min( $count, ((($page - 1) * $perPage) + $perPage) ),
            'numPages'    => $numPages,
            'next'        => ($page < $numPages) ? ($page + 1) : false,
            'prev'        => ($page > 1)         ? ($page - 1) : false
        ];
        
        $data['posts'] = $this->jql->select('*')->where('published = 1')->order('created', 'DESC')->limit($perPage)->offset( ($page - 1) * $perPage )->fetch();

        # Add the archive info:
        $data['year']  = null;
        $data['month'] = null;
        
        return $this->blogInstance->render('blog-index.twig', $data);
    }
    
    # Single post page:
    public function post( $slug )
    {
        
    }
    
    # Year archive page:
    public function archiveYear( $year )
    {
        $from = mktime(0, 0, 0, 1, 1, $year);
        $to   = mktime(23, 59, 59, 12, 31, $year);
        
        $count    = $this->jql->select([ 'file' ])->where('published = 1 AND created >= '.$from.' AND created <= '.$to)->count();
        $perPage  = $this->blogInstance->getSetting('postsPerPage');
        $numPages = ceil( $count / $perPage ); 
        $page    = (\micro_get_param('page') == null) ? 1 : \micro_get_param('page');
    
        # Add the pagination object to twig:
        $data['pagination'] = [
            'url'         => \micro_url($this->base.'/'.$year.'/?page={page}'),
            'currentPage' => $page,
            'perPage'     => $perPage,
            'from'        => (($page - 1) * $perPage) + 1,
            'to'          => min( $count, ((($page - 1) * $perPage) + $perPage) ),
            'numPages'    => $numPages,
            'next'        => ($page < $numPages) ? ($page + 1) : false,
            'prev'        => ($page > 1)         ? ($page - 1) : false
        ];
        
        $data['posts'] = $this->jql->select('*')->where('published = 1 AND created >= '.$from.' AND created <= '.$to)->order('created', 'DESC')->limit($perPage)->offset( ($page - 1) * $perPage )->fetch();

        # Add the archive info:
        $data['year']  = strftime('%Y', $from);
        $data['month'] = null;
        
        $data = [
            'meta' => [
                'title'        => \micro_set_title('Archive: '.$data['year'].' - Blog'),
                'description'  => '',
                'canonicalURL' => \micro_set_canonical_url( trim($this->base, '/').'/'.$year.'/')
            ]
        ];
        
        return $this->blogInstance->render('blog-archive.twig', $data);
    }
    
    # Month + Year archive page:
    public function archiveMonth( $year, $month )
    {
        $from = mktime(0, 0, 0, $month, 1, $year);
        $to   = mktime(23, 59, 59, cal_days_in_month(CAL_GREGORIAN, $month, $year), $year);
        
        $count    = $this->jql->select([ 'file' ])->where('published = 1 AND created >= '.$from.' AND created <= '.$to)->count();
        $perPage  = $this->blogInstance->getSetting('postsPerPage');
        $numPages = ceil( $count / $perPage ); 
        $page    = (\micro_get_param('page') == null) ? 1 : \micro_get_param('page');
    
        # Add the pagination object to twig:
        $data['pagination'] = [
            'url'         => \micro_url($this->base.'/'.$year.'/'.$month.'/?page={page}'),
            'currentPage' => $page,
            'perPage'     => $perPage,
            'from'        => (($page - 1) * $perPage) + 1,
            'to'          => min( $count, ((($page - 1) * $perPage) + $perPage) ),
            'numPages'    => $numPages,
            'next'        => ($page < $numPages) ? ($page + 1) : false,
            'prev'        => ($page > 1)         ? ($page - 1) : false
        ];
        
        $data['posts'] = $this->jql->select('*')->where('published = 1 AND created >= '.$from.' AND created <= '.$to)->order('created', 'DESC')->limit($perPage)->offset( ($page - 1) * $perPage )->fetch();

        # Add the archive info:
        $data['year']  = strftime('%Y', $from);
        $data['month'] = strftime('%B', $from);
        
        $data = [
            'meta' => [
                'title'        => \micro_set_title('Archive: '.$data['month'].' '.$data['year'].' - Blog'),
                'description'  => '',
                'canonicalURL' => \micro_set_canonical_url( trim($this->base, '/').'/'.$year.'/')
            ]
        ];
        
        return $this->blogInstance->render('blog-archive.twig', $data);
    }
    
    # Category post list:
    public function category( $slug )
    {
        $count    = $this->jql->select([ 'file' ])->where('published = 1 AND categorySlugList CONTAINS '.$slug)->count();
        $perPage  = $this->blogInstance->getSetting('postsPerPage');
        $numPages = ceil( $count / $perPage ); 
        $page    = (\micro_get_param('page') == null) ? 1 : \micro_get_param('page');
    
        # Add the pagination object to twig:
        $data['pagination'] = [
            'url'         => \micro_url($this->base.'/category/'.$slug.'/?page={page}'),
            'currentPage' => $page,
            'perPage'     => $perPage,
            'from'        => (($page - 1) * $perPage) + 1,
            'to'          => min( $count, ((($page - 1) * $perPage) + $perPage) ),
            'numPages'    => $numPages,
            'next'        => ($page < $numPages) ? ($page + 1) : false,
            'prev'        => ($page > 1)         ? ($page - 1) : false
        ];
        
        $data['posts'] = $this->jql->select('*')->where('published = 1 AND categorySlugList CONTAINS '.$slug)->order('created', 'DESC')->limit($perPage)->offset( ($page - 1) * $perPage )->fetch();

        # Add the archive info:
        $data['category'] = $this->blogInstance->getCategory($slug);
        
        $data = [
            'meta' => [
                'title'        => \micro_set_title($data['category']['name'].' Posts - Blog'),
                'description'  => '',
                'canonicalURL' => \micro_set_canonical_url( trim($this->base, '/').'/category/'.$slug.'/')
            ]
        ];
        
        return $this->blogInstance->render('blog-category.twig', $data);
    }
    
    # Tag post list:
    public function tag( $slug )
    {
        
    }
    
    # Author post list:
    public function author( $slug )
    {
        
    }
}