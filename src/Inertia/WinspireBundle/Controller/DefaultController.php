<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Suitcase;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultController extends Controller
{
    public function featuredPackagesAction()
    {
        $suitcase = $this->getSuitcase();
        
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.active = 1 ORDER BY p.parent_header ASC, p.is_default DESC'
        );
        
        $packages = $query->getResult();
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
            if($package->getIsDefault() && $package->getIsOnHome()) {
                $count = 1;
                $index = $package->getId();
                
                // Determine whether to show the "Add to Suitcase" button based 
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                if($suitcase) {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $index) {
                            $available = false;
                        }
                    }
                }
                
                $defaultPackages[$index] = array('package' => $package, 'count' => 1, 'available' => $available);
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
            }
            
            $defaultPackages[$index]['count'] = $count;
        }
        
        $keys = array_rand($defaultPackages, 17);
        $temp = array();
        foreach($keys as $key) {
            $temp[] = $defaultPackages[$key];
        }
        
        return $this->render('InertiaWinspireBundle:Default:featuredPackages.html.twig',
            array(
                'packages' => $temp
            )
        );
    }
    
    public function indexAction()
    {
        return $this->render('InertiaWinspireBundle:Default:index.html.twig');
    }
    
    public function loginWidgetAction()
    {
        $csrfToken = $this->get('form.csrf_provider')->generateCsrfToken('authenticate');
        
        return $this->render('InertiaWinspireBundle:Default:loginWidget.html.twig', 
            array(
                'csrf_token' => $csrfToken
            )
        );
    }
    
    public function lovedByAction()
    {
        $em = $this->getDoctrine()->getManager();
        $clientLogos = $em->getRepository('InertiaWinspireBundle:ClientLogo')->findAll();
        
        $keys = array_rand($clientLogos, 5);
        
        $temp = array();
        foreach($keys as $key) {
            $temp[] = $clientLogos[$key];
        }
        
        return $this->render('InertiaWinspireBundle:Default:lovedBy.html.twig',
            array(
                'clientLogos' => $temp
            )
        );
    }
    
    public function packageListAction($slug)
    {
        $session = $this->getRequest()->getSession();
        $suitcase = $this->getSuitcase();
        
        $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
        $filterTree = $repo->childrenHierarchy();
        $filterTree = $filterTree[0]['__children'];
        
        // Accumulate an array of possible category IDs
        $catIds = array();
        $category = $repo->findOneBySlug($slug);
        $catIds[] = $category->getId();
        foreach($category->getChildren() as $child) {
            $catIds[] = $child->getId();
            foreach($child->getChildren() as $sub) {
                $catIds[] = $sub->getId();
            }
        }
        
        $em = $this->getDoctrine()->getManager();
        
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p, c FROM InertiaWinspireBundle:Package p JOIN p.categories c WHERE c.id IN (:ids) AND p.picture IS NOT NULL AND p.active = 1 ORDER BY p.parent_header ASC, p.is_default DESC'
            )->setParameter('ids', $catIds);
        }
        else {
            $query = $em->createQuery(
                'SELECT p, c FROM InertiaWinspireBundle:Package p JOIN p.categories c WHERE c.id IN (:ids) AND p.is_private != 1 AND p.picture IS NOT NULL AND p.active = 1 ORDER BY p.parent_header ASC, p.is_default DESC'
            )->setParameter('ids', $catIds);
        }
        
        $packages = $query->getResult();
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
            if($package->getIsDefault()) {
                $count = 1;
                $index = $package->getId();
                
                // Determine whether to show the "Add to Suitcase" button based 
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                if($suitcase) {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $index) {
                            $available = false;
                        }
                    }
                }
                
                $defaultPackages[$index] = array('package' => $package, 'count' => 1, 'available' => $available);
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
            }
            
            if (isset($index)) {
                $defaultPackages[$index]['count'] = $count;
            }
        }
        
        $session->set('packagePath', $slug);
        
        return $this->render(
            'InertiaWinspireBundle:Default:packageList.html.twig',
            array(
                'catIds' => $catIds,
                'packages' => $defaultPackages,
                'filterTree' => $filterTree,
                'rootCat' => $category->getId()
            )
        );
    }
    
    public function packageListJsonAction(Request $request)
    {
        $session = $this->getRequest()->getSession();
        $suitcase = $this->getSuitcase();
        
        if ($categories = $request->query->get('category')) {
            $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
            $filterTree = $repo->childrenHierarchy();
            $filterTree = $filterTree[0]['__children'];
            
            $matchedCategories = array();
            foreach($filterTree as $parent) {
                if(array_key_exists($parent['id'], $categories)) {
//                    $matchedCategories[$parent['id']][] = $parent['id'];
                    $matchedCategories[] = $parent['id'];
                    
                    // if a parent category is chosen, then add all child categories
                    if(array_search($parent['id'], $categories[$parent['id']]) !== FALSE) {
                        foreach($parent['__children'] as $child) {
//                            $matchedCategories[$parent['id']][] = $child['id'];
                            $matchedCategories[] = $child['id'];
                        }
                    }
                    else {
                        foreach($categories[$parent['id']] as $child) {
//                            $matchedCategories[$parent['id']][] = $child;
                            $matchedCategories[] = $child;
                        }
                    }
                }
            }
            
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            $qb->innerJoin('p.categories', 'c');
            
            if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
                $qb->andWhere('p.is_private != 1');
            }
            $qb->andWhere('p.picture IS NOT NULL');
            $qb->andWhere('p.active = 1');
            $qb->andWhere($qb->expr()->in('c.id', $matchedCategories));
            
            if($request->query->get('sortOrder') == 'alpha-desc') {
                $qb->orderBy('p.parent_header', 'DESC');
            }
            else {
                $qb->orderBy('p.parent_header', 'ASC');
            }
            
            $qb->addOrderBy('p.is_default', 'DESC');
            
            $packages = $qb->getQuery()->getResult();
        }
        else {
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            
            if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
                $qb->andWhere('p.is_private != 1');
            }
            $qb->andWhere('p.picture IS NOT NULL');
            $qb->andWhere('p.active = 1');
            
            if($request->query->get('sortOrder') == 'alpha-desc') {
                $qb->orderBy('p.parent_header', 'DESC');
            }
            else {
                $qb->orderBy('p.parent_header', 'ASC');
            }
            $qb->addOrderBy('p.is_default', 'DESC');
            
            $packages = $qb->getQuery()->getResult();
        }
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
            if($package->getIsDefault()) {
                $count = 1;
                $index = $package->getId();
                
                // Determine whether to show the "Add to Suitcase" button based
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                
                if($suitcase) {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $package->getId()) {
                            $available = false;
                        }
                    }
                }
                
                $defaultPackages[$index] = array('package' => $package, 'count' => 1, 'available' => $available);
                
                $defaultPackages[$index]['new'] = false;
                $defaultPackages[$index]['popular'] = false;
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
            }
            
            if (isset($index)) {
                $defaultPackages[$index]['new'] = $defaultPackages[$index]['new'] || $package->getIsNew();
                $defaultPackages[$index]['popular'] = $defaultPackages[$index]['popular'] || $package->getIsBestSeller();
                $defaultPackages[$index]['count'] = $count;
            }
        }
        
        
        if($request->query->get('filter') == 'popular') {
            $defaultPackages = array_filter($defaultPackages, function ($item) {
                if($item['popular']) {
                    return true;
                }
                return false;
            });
        }
        
        if($request->query->get('filter') == 'newest') {
            $defaultPackages = array_filter($defaultPackages, function ($item) {
                if($item['new']) {
                    return true;
                }
                return false;
            });
        }
        
        
        
        return $this->render(
            'InertiaWinspireBundle:Default:packages.html.twig',
             array(
                'packages' => $defaultPackages,
            )
        );
    }
    
    public function packageDetailAction($slug)
    {
        $suitcase = $this->getSuitcase();
        
        $session = $this->getRequest()->getSession();
        $packagePath = $session->get('packagePath');
        
        $em = $this->getDoctrine()->getManager();
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.picture IS NOT NULL AND p.active = 1 AND p.slug = :slug'
            )->setParameter('slug', $slug);
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.active = 1 AND p.slug = :slug'
            )->setParameter('slug', $slug);
        }
        
        $package = $query->getResult();
        
        if (!$package) {
            throw $this->createNotFoundException();
        }
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.picture IS NOT NULL AND p.active = 1 AND p.parent_header = :ph ORDER BY p.parent_header ASC, p.is_default DESC'
            )->setParameter('ph', $package[0]->getParentHeader());
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.active = 1 AND p.picture IS NOT NULL AND p.parent_header = :ph ORDER BY p.parent_header ASC, p.is_default DESC'
            )->setParameter('ph', $package[0]->getParentHeader());
        }
        
        $packages = $query->getResult();
        
        
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
            
            // Determine whether to show the "Add to Suitcase" button based
            // on the Packages already contained in the session.
            // TODO refactor for a more efficient algorithm
            $available = true;
            if($suitcase) {
                foreach($suitcase->getItems() as $i) {
                    // We already have this item in our cart;
                    // so we can stop here...
                    if($i->getPackage()->getId() == $package->getId()) {
                        $available = false;
                    }
                }
            }
            
            if($package->getIsDefault()) {
                $count = 1;
                $index = $package->getId();
                $defaultPackages[$index] = array('package' => $package, 'count' => 1, 'available' => $available);
                $defaultPackages[$index]['variants'] = array($package);
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
                $defaultPackages[$index]['variants'][] = $package;
            }
            
            if (isset($index)) {
                $defaultPackages[$index]['count'] = $count;
            }
        }
        
        
        return $this->render(
            'InertiaWinspireBundle:Default:packageDetail.html.twig',
            array(
                'available' => $defaultPackages[$index]['available'],
                'package' => $defaultPackages[$index]['package'],
                'packagePath' => $packagePath,
                'slug' => $slug,
                'variants' => $defaultPackages[$index]['variants']
            )
        );
    }
    
    
    public function packageDownloadAction($versionId)
    {
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT v FROM InertiaWinspireBundle:ContentPackVersion v WHERE v.id = :id'
        )->setParameter('id', $versionId);
        
        try {
            $contentPackVersion = $query->getSingleResult();
        }
        catch (\Exception $e) {
            throw $this->createNotFoundException();
        }
        
        // Find a package using this Content Pack to give it file name
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.sfContentPackId = :sfId'
        )
            ->setParameter('sfId', $contentPackVersion->getContentPack()->getSfId())
            ->setMaxResults(1);
        ;
        
        
        try {
            $package = $query->getSingleResult();
            $name = $package->getSlug() . '.zip';
        }
        catch(\Exception $e) {
            $name = $contentPackVersion->getId() . '.zip';
        }
        
        $directory = $this->container->getParameter('kernel.root_dir') . '/documents/';
        $directory .= $contentPackVersion->getSfId() . '/';
        $filename = $contentPackVersion->getSfId() . '.zip';
        
        $response = new Response();
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $name . '"');
        
        return $response->setContent(file_get_contents($directory . $filename));
    }
    
    
    public function packageSearchAction(Request $request)
    {
        $sphinxSearch = $this->get('search.sphinxsearch.search');
        
        if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $sphinxSearch->setFilter('isprivate', array(1), true);
        }
        
        $sphinxSearch->setMatchMode(SPH_MATCH_ANY);
        $searchResults = $sphinxSearch->search($request->query->get('q'), array('Packages'), array(), false);
        
        $matches = array();
        if(isset($searchResults['matches'])) {
            foreach($searchResults['matches'] as $key => $value) {
                $matches[] = $key;
            }
        }
        
        
        $session = $this->getRequest()->getSession();
        $suitcase = $this->getSuitcase();
        
        if(!empty($matches)) {
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            
            $qb->andWhere($qb->expr()->in('p.id', $matches));
            $qb->andWhere('p.active = 1');
            
            
            if($request->query->get('sortOrder') == 'alpha-desc') {
                $qb->orderBy('p.parent_header', 'DESC');
            }
            else {
                $qb->orderBy('p.parent_header', 'ASC');
            }
            $qb->addOrderBy('p.is_default', 'DESC');
            
            $packages = $qb->getQuery()->getResult();
        }
        else {
            $packages = array();
        }
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
//echo $package->getParentHeader();
//echo "<br/>\n";
            if($package->getIsDefault()) {
                $count = 1;
                $index = $package->getId();
                
                // Determine whether to show the "Add to Suitcase" button based
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                
                if($suitcase) {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $package->getId()) {
                            $available = false;
                        }
                    }
                }
                
                $defaultPackages[$index] = array('package' => $package, 'count' => 1, 'available' => $available);
                
                $defaultPackages[$index]['new'] = false;
                $defaultPackages[$index]['popular'] = false;
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
            }
            
            if(isset($index)) {
                $defaultPackages[$index]['new'] = $defaultPackages[$index]['new'] || $package->getIsNew();
                $defaultPackages[$index]['popular'] = $defaultPackages[$index]['popular'] || $package->getIsBestSeller();
                $defaultPackages[$index]['count'] = $count;
            }
        }
        
        
        if($request->query->get('filter') == 'popular') {
            $defaultPackages = array_filter($defaultPackages, function ($item) {
                if($item['popular']) {
                    return true;
                }
                return false;
            });
        }
        
        if($request->query->get('filter') == 'newest') {
            $defaultPackages = array_filter($defaultPackages, function ($item) {
                if($item['new']) {
                    return true;
                }
                return false;
            });
        }
        
        
        
        return $this->render(
            'InertiaWinspireBundle:Default:packages.html.twig',
            array(
                'packages' => $defaultPackages,
            )
        );
    }
    
    
    public function packageSearchJsonAction(Request $request)
    {
        $response = new JsonResponse();
        $sphinxSearch = $this->get('search.sphinxsearch.search');
        
        if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $sphinxSearch->setFilter('isprivate', array(1), true);
        }
        
        $sphinxSearch->setMatchMode(SPH_MATCH_ANY);
        $searchResults = $sphinxSearch->search($request->query->get('q'), array('Packages'), array(), false);
        
        $matches = array();
        if(isset($searchResults['matches'])) {
            foreach($searchResults['matches'] as $key => $value) {
                $matches[] = $key;
            }
        }
        
        if(!empty($matches)) {
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            $qb->andWhere($qb->expr()->in('p.id', $matches));
            $qb->andWhere('p.active = 1');
            $qb->addOrderBy('p.parent_header', 'ASC');
            $qb->addOrderBy('p.is_default', 'DESC');
            $packages = $qb->getQuery()->getResult();
        }
        else {
            $packages = array();
        }
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        $currentHeader = '';
        $count = 0;
        foreach($packages as $package) {
            if($package->getIsDefault()) {
                $count = 1;
                $index = $package->getSlug();
                
                $available = true;
                
                $defaultPackages[$index] = array('package' =>
                    array(
                        'slug'    => $package->getSlug(),
                        'title' => $package->getParentHeader(),
                        'image' => $package->getThumbnail()
                    ),
                    'count' => 1
                );
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
            }
            
            if(isset($index)) {
                $defaultPackages[$index]['count'] = $count;
            }
        }
        
        
        return $response->setData(
            array(
                'packages' => array_slice($defaultPackages, 0, 10)
            )
        );
    }
    
    
    public function siteNavAction()
    {
        $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
        $categoryTree = $repo->childrenHierarchy();
        
        $categoryTree = $categoryTree[0]['__children'];
        
        // Assign categories to columns for the drop-down navigation
        $temp = array();
        foreach($categoryTree as $subtree) {
            $temp[$subtree['col']][] = $subtree;
        }
        
        
        if(count($temp) < 4) {
            $temp[] = array();
        }
        
        return $this->render(
            'InertiaWinspireBundle:Default:siteNav.html.twig',
            array('categories' => $temp)
        );
    }
    
    
    public function testimonialAction()
    {
        $em = $this->getDoctrine()->getManager();
        $testimonials = $em->getRepository('InertiaWinspireBundle:Testimonial')->findAll();
        
        $key = array_rand($testimonials, 1);
        
        return $this->render('InertiaWinspireBundle:Default:testimonial.html.twig',
            array(
                'testimonial' => $testimonials[$key]
            )
        );
    }
    
    
    public function wordpressAction()
    {
        $env = $this->container->getParameter('kernel.environment');
        $posts = array();
        
        
        // TODO Why is Wordpress such a nightmare?
        // There has to be a shorter method for loading the Wordpress core.
        if($env == 'prod') {
            if(!defined('SHORTINIT')) {
                define('SHORTINIT', true);
            }
            
//            define('WP_USE_THEMES', false);
            define('ABSPATH', '/var/www/blog.winspireme.com/');
            define('WP_CONTENT_URL', 'http://winspireme.com/blog/wp-content');
            
            // Default load
            require( ABSPATH . 'wp-config.php' );
            
            // Loading code from wp-settings after SHORTINIT
            require( ABSPATH . WPINC . '/l10n.php' );
            require( ABSPATH . WPINC . '/formatting.php' );
            require( ABSPATH . WPINC . '/capabilities.php' );
            require( ABSPATH . WPINC . '/query.php' );
            require( ABSPATH . WPINC . '/user.php' );
            require( ABSPATH . WPINC . '/meta.php' );
            require( ABSPATH . WPINC . '/general-template.php' );
            require( ABSPATH . WPINC . '/link-template.php' );
            require( ABSPATH . WPINC . '/post.php' );
            require( ABSPATH . WPINC . '/comment.php' );
            require( ABSPATH . WPINC . '/rewrite.php' );
            require( ABSPATH . WPINC . '/script-loader.php' );
            require( ABSPATH . WPINC . '/theme.php' ); 
            require( ABSPATH . WPINC . '/taxonomy.php' );
            require( ABSPATH . WPINC . '/class-wp-walker.php' );
            require( ABSPATH . WPINC . '/category.php' );
            require( ABSPATH . WPINC . '/category-template.php' );
            require( ABSPATH . WPINC . '/post-thumbnail-template.php' );
            require( ABSPATH . WPINC . '/shortcodes.php' );
            require( ABSPATH . WPINC . '/media.php' );
            
            create_initial_taxonomies();
            create_initial_post_types();
            
            require( ABSPATH . WPINC . '/pluggable.php' );
            
            wp_set_internal_encoding();
            wp_functionality_constants( );
            
            $GLOBALS['wp_the_query'] = new \WP_Query();
            $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
            $GLOBALS['wp_rewrite'] = new \WP_Rewrite();
            $GLOBALS['wp'] = new \WP();
            $GLOBALS['wp']->init();
            
            $wpPost1 = get_posts(array('cat' => 230, 'showposts' => 1));
            $wpPost2 = get_posts(array('cat' => 231, 'showposts' => 1));
            $wpPost3 = get_posts(array('cat' => 232, 'showposts' => 1));
            $wpPost4 = get_posts(array('cat' => 233, 'showposts' => 1));
            
            if(count($wpPost1) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost1[0]->ID), array(160, 100)); 
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost1[0]->post_title,
                    'link' => get_permalink($wpPost1[0]->ID),
                    'date' => new \DateTime($wpPost1[0]->post_date)
                );
            }
            
            if(count($wpPost2) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost2[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost2[0]->post_title,
                    'link' => get_permalink($wpPost2[0]->ID),
                    'date' => new \DateTime($wpPost2[0]->post_date)
                );
            }
            
            if(count($wpPost3) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost3[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost3[0]->post_title,
                    'link' => get_permalink($wpPost3[0]->ID),
                    'date' => new \DateTime($wpPost3[0]->post_date)
                );
            }
            
            if(count($wpPost4) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost4[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost4[0]->post_title,
                    'link' => get_permalink($wpPost4[0]->ID),
                    'date' => new \DateTime($wpPost4[0]->post_date)
                );
            }
        }
        else {
            // All non-production environments will load the sample posts
            $posts[] = array(
                'image' => '/img/home-ln-placeholder-1.png',
                'title' => 'An Open Plea to Hold Better Events',
                'link' => '#',
                'date' => new \DateTime('October 24, 2012')
            );
            
            $posts[] = array(
                'image' => '/img/home-ln-placeholder-2.png',
                'title' => '6 Ways to Engage with Millenials',
                'link' => '#',
                'date' => new \DateTime('July 26, 2012')
            );
            
            $posts[] = array(
                'image' => '/img/home-ln-placeholder-3.png',
                'title' => 'How Social Media & Fundraising Fit Together',
                'link' => '#',
                'date' => new \DateTime('August 15, 2012')
            );
            
            $posts[] = array(
                'image' => '/img/home-ln-placeholder-4.png',
                'title' => 'Expensive Travels',
                'link' => '#',
                'date' => new \DateTime('July 26, 2012')
            );
        }
        
        
        return $this->render('InertiaWinspireBundle:Default:wordpress.html.twig',
            array(
                'posts' => $posts
            )
        );
    }
    
    
    protected function getSuitcase()
    {
        $em = $this->getDoctrine()->getManager();
        
        $session = $this->getRequest()->getSession();
        $sid = $session->get('sid');
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            if($sid) {
                $query = $em->createQuery(
                    'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.id = :id ORDER BY i.updated DESC'
                )
                ->setParameter('id', $sid)
                ;
                
                try {
                    $suitcase = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    //                    throw $this->createNotFoundException();
                    $suitcase = new Suitcase();
                }
                
                return $suitcase;
            }
            else {
                return false;
            }
        }
        
        
        
        
        
        
        
        
        
        // Establish which suitcase to use for current user
        $user = $this->getUser();
        
        if(!$user) {
            return new Suitcase();
        }
        
//        $session = $this->getRequest()->getSession();
//        $em = $this->getDoctrine()->getManager();
        
        // First, check the current session for a suitcase id
//        $sid = $session->get('sid');
        if($sid) {
            //echo 'Found SID, step 1: ' . $sid . "<br/>\n";
            $query = $em->createQuery(
                'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i LEFT JOIN i.package p WHERE s.user = :user_id AND s.id = :id ORDER BY i.updated DESC'
            )
            ->setParameter('user_id', $user->getId())
            ->setParameter('id', $sid);
            
            try {
                $suitcase = $query->getSingleResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                // If the suitcase we were expecting doesn't exist, we'll create a new one
                //                throw $this->createNotFoundException();
                $suitcase = new Suitcase();
                $suitcase->setUser($user);
                $suitcase->setPacked(false);
                $em->persist($suitcase);
                $em->flush();
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
            
            return $suitcase;
        }
        // Second, query for the most recent suitcase (used as default)
        else {
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i LEFT JOIN i.package p WHERE s.user = :user_id ORDER BY s.updated DESC, i.updated DESC'
            )->setParameter('user_id', $user->getId());
            
            try {
                $suitcase = $query->getResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                throw $this->createNotFoundException();
            }
            
            if(count($suitcase) > 0) {
                $suitcase = $suitcase[0];
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
            else {
                // Third, no existing suitcases found for this account... create a new one
                $suitcase = new Suitcase();
                $suitcase->setUser($user);
                $suitcase->setPacked(false);
                
                $em->persist($suitcase);
                $em->flush();
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
        }
    }
}
