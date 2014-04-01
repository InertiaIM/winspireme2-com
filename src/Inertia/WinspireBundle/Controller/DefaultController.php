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
        $locale = $this->getRequest()->getLocale();
        $suitcase = $this->get('winspire.suitcase.manager')->getSuitcase();
        
        // First we select all the packages in the pool of "on home"
        // (filtered by locale for non-admin users)
        $em = $this->getDoctrine()->getManager();
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.active = 1 AND p.available = 1 AND p.is_on_home = 1 AND p.is_default = 1'
            );
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o WHERE p.is_private != 1 AND p.active = 1 AND p.available = 1 AND p.is_on_home = 1 AND p.is_default = 1 AND (o.code = :code)'
            );
            $query->setParameter('code', $locale);
        }
        
        // Next we put the parentHeaders into a regular array
        // for running them through array_rand
        $defaultPackages = $query->getResult();
        $temp = array();
        foreach ($defaultPackages as $p) {
            $temp[] = $p->getParentHeader();
        }
        
        // Limit the output to 17 packages to prevent too much load time
        if (count($temp) > 17) {
            $keys = array_rand($temp, 17);
        }
        else {
            $keys = array_rand($temp, count($temp));
        }
        
        $parentHeaders = array();
        foreach ($keys as $key) {
            $parentHeaders[] = $temp[$key];
        }
        
        // Setup new query to get counts of variants for the
        // randomly chosen packages (parentHeaders)
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.active = 1 AND p.available = 1 AND p.parent_header in (:ph)'
            );
            $query->setParameter('ph', $parentHeaders);
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o WHERE p.is_private != 1 AND p.active = 1 AND p.available = 1 AND (o.code = :code) AND p.parent_header in (:ph)'
            );
            $query->setParameter('code', $locale);
            $query->setParameter('ph', $parentHeaders);
        }
        
        $packages = $query->getResult();
        
        
        $featuredPackages = array();
        foreach($packages as $package) {
            $featuredPackages[$package->getParentHeader()]['packages'][] = $package->getCode();
            
            if ($package->getIsDefault()) {
                $featuredPackages[$package->getParentHeader()]['default'] = $package;
                
                // Determine whether to show the "Add to Suitcase" button based
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                
                if ($suitcase && $suitcase != 'new') {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $package->getId()) {
                            $available = false;
                        }
                    }
                }
                
                $featuredPackages[$package->getParentHeader()]['available'] = $available;
            }
        }
        
        // Special considerations for Admin users who will see all locales
        // and all "default" versions.  So we want them to see the "default"
        // as the US version (ignoring the defaults in others locales).
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            foreach($packages as $package) {
                if ($package->getIsDefault()) {
                    $origins = $package->getOrigins();
                    $localeArray = array();
                    foreach($origins as $o) {
                        $localeArray[] = $o->getCode();
                    }
                    
                    if (in_array('us', $localeArray)) {
                        $featuredPackages[$package->getParentHeader()]['default'] = $package;
                    }
                }
            }
        }
        
        foreach ($featuredPackages as $key => $p) {
            $featuredPackages[$key]['count'] = count($p['packages']);
        }
        
        return $this->render('InertiaWinspireBundle:Default:featuredPackages.html.twig',
            array(
                'packages' => $featuredPackages,
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
        $request = $this->getRequest();
        $q = $request->query->get('q');
        
        $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
        $filterTree = $repo->childrenHierarchy();
        $filterTree = $filterTree[0]['__children'];
        
        // Accumulate an array of possible category IDs
        $catIds = array();
        
        $category = $repo->findOneBySlug($slug);
        if(!$category) {
            throw $this->createNotFoundException();
        }
        
        $catIds[] = $category->getId();
        foreach($category->getChildren() as $child) {
            $catIds[] = $child->getId();
            foreach($child->getChildren() as $sub) {
                $catIds[] = $sub->getId();
            }
        }
        
        return $this->render(
            'InertiaWinspireBundle:Default:packageList.html.twig',
            array(
                'catIds' => $catIds,
                'filterTree' => $filterTree,
                'rootCat' => $category->getId(),
                'q' => $q
            )
        );
    }
    
    
    public function packageDetailAction($slug)
    {
        $locale = $this->getRequest()->getLocale();
        
        $suitcase = $this->get('winspire.suitcase.manager')->getSuitcase();
        
        $session = $this->getRequest()->getSession();
        $packagePath = $session->get('packagePath');
        
        $em = $this->getDoctrine()->getManager();
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p, o FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o WHERE p.picture IS NOT NULL AND (p.active = 1 OR p.seasonal = 1) AND (p.available = 1) AND p.slug = :slug'
            )
                ->setParameter('slug', $slug);
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o WHERE p.is_private != 1 AND p.picture IS NOT NULL AND (p.active = 1 OR p.seasonal = 1) AND (p.available = 1) AND p.slug = :slug AND o.code = :code'
            )
                ->setParameter('slug', $slug)
                ->setParameter('code', $locale);
        }
        
        $package = $query->getResult();
        
        if (!$package) {
            throw $this->createNotFoundException();
        }
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.picture IS NOT NULL AND (p.active = 1 OR p.seasonal = 1) AND (p.available = 1) AND p.parent_header = :ph ORDER BY p.parent_header ASC, p.is_default DESC'
            )
                ->setParameter('ph', $package[0]->getParentHeader())
            ;
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o WHERE p.is_private != 1 AND (p.active = 1 OR p.seasonal = 1) AND (p.available = 1) AND p.picture IS NOT NULL AND p.parent_header = :ph AND o.code = :code ORDER BY p.parent_header ASC, p.is_default DESC'
            )
                ->setParameter('ph', $package[0]->getParentHeader())
                ->setParameter('code', $locale)
            ;
        }
        
        $packages = $query->getResult();
        
        
        $latest = 0;
        $match = false;
        $defaultPackages = array();
        foreach($packages as $package) {
            $defaultPackages['variants'][] = $package;
            
            if ($package->getIsDefault()) {
                $defaultPackages['default'] = $package;
            }
            
            if ($suitcase && $suitcase != 'new') {
                // Add tweak to determine the most recently added variant
                // from the user's Suitcase to bring them back to the
                // specific variant when visiting the detail page again.
                foreach($suitcase->getItems() as $i) {
                    if($i->getPackage()->getId() == $package->getId()) {
                        if ($i->getCreated()->getTimestamp() > $latest) {
                            $latest = $i->getCreated()->getTimestamp();
                            $match = $package;
                        }
//                    break;
                    }
                }
            }
        }
        
        // Special considerations for Admin users who will see all locales
        // and all "default" versions.  So we want them to see the "default"
        // as the US version (ignoring the defaults in others locales).
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            foreach($packages as $package) {
                if ($package->getIsDefault()) {
                    $origins = $package->getOrigins();
                    $localeArray = array();
                    foreach($origins as $o) {
                        $localeArray[] = $o->getCode();
                    }
                    
                    if (in_array('us', $localeArray)) {
                        $defaultPackages['default'] = $package;
                    }
                }
            }
        }
        
//if($match) {
//print_r($match->getCode());
//    
//echo "<br/><br/>"; exit;
//}
//print_r($defaultPackages); exit;
        
        
        $packageIds = array();
        if ($suitcase && $suitcase != 'new') {
            foreach ($suitcase->getItems() as $item) {
                $packageIds[] = $item->getPackage()->getId();
            }
        }

        if (!isset($defaultPackages['default'])) {
            throw $this->createNotFoundException();
        }
        
        return $this->render(
            'InertiaWinspireBundle:Default:packageDetail.html.twig',
            array(
                'package' => $defaultPackages['default'],
                'packageIds' => $packageIds,
                'packagePath' => $packagePath,
                'slug' => $slug,
                'variants' => $defaultPackages['variants'],
                'match' => $match
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
        $locale = $this->getRequest()->getLocale();
        
        $q = $request->attributes->get('q');
        
        if ($request->query->has('q')) {
            $q = $request->query->get('q');
        }
        
        $em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
        
        $qb->select(array('p', 'o'));
        $qb->from('InertiaWinspireBundle:Package', 'p');
        
        if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('p.is_private != 1');
            $qb->innerJoin('p.origins', 'o', 'WITH', 'o.code = \'' . $locale . '\'');
        }
        else {
            $qb->innerJoin('p.origins', 'o');
        }
        
        $qb->andWhere('p.active = 1 OR p.seasonal = 1');
        $qb->andWhere('p.available = 1');
        
        switch ($request->query->get('sortOrder')) {
            case 'alpha-desc':
                $qb->orderBy('p.parent_header', 'DESC');
                break;
            case 'alpha-asc':
                $qb->orderBy('p.parent_header', 'ASC');
                break;
            case 'price-desc':
                $qb->orderBy('p.cost', 'DESC');
                break;
            case 'price-asc':
                $qb->orderBy('p.cost', 'ASC');
                break;
            default:
                $qb->orderBy('p.parent_header', 'ASC');
        }
        
        if ($request->attributes->has('catIds') || $request->query->has('category')) {
            $catIds = $request->attributes->get('catIds');

            if ($request->query->has('category')) {
                $categories = $request->query->get('category');
                $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
                $filterTree = $repo->childrenHierarchy();
                $filterTree = $filterTree[0]['__children'];

                $catIds = array();
                foreach($filterTree as $parent) {
                    if(array_key_exists($parent['id'], $categories)) {
                        $catIds[] = $parent['id'];

                        // if a parent category is chosen, then add all child categories
                        if(array_search($parent['id'], $categories[$parent['id']]) !== FALSE) {
                            foreach($parent['__children'] as $child) {
                                $catIds[] = $child['id'];
                            }
                        }
                        else {
                            foreach($categories[$parent['id']] as $child) {
                                $catIds[] = $child;
                            }
                        }
                    }
                }
            }

            $qb->innerJoin('p.categories', 'c');
            $qb->andWhere($qb->expr()->in('c.id', $catIds));
        }
        
        if ($q) {
            $sphinxSearch = $this->get('search.sphinxsearch.search');
            
            if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
                $sphinxSearch->setFilter('isprivate', array(1), true);
            }

            $searchResults = $sphinxSearch->search($q . '*', array('Packages'), array('result_limit' => 1000, 'result_offset' => 0), false);
            
            $matches = array();
            if(isset($searchResults['matches'])) {
                foreach($searchResults['matches'] as $key => $value) {
                    $matches[] = $key;
                }
            }
            
            if ($matches) {
                $qb->andWhere($qb->expr()->in('p.id', $matches));
            }
            else {
                return $this->render(
                    'InertiaWinspireBundle:Default:packages.html.twig',
                    array(
                        'packages' => array()
                    )
                );
            }
        }
        
        $packages = $qb->getQuery()->getResult();
        
        $suitcase = $this->get('winspire.suitcase.manager')->getSuitcase();
        
        // TODO this is too complex.  Break the Packages and Variants into
        // separate entities to simplify the queries.
        $defaultPackages = array();
        foreach($packages as $package) {
            $defaultPackages[$package->getParentHeader()]['packages'][] = $package->getCode();
            
            if ($package->getIsDefault()) {
                $defaultPackages[$package->getParentHeader()]['default'] = $package;

                // Determine whether to show the "Add to Suitcase" button based
                // on the Packages already contained in the session.
                // TODO refactor for a more efficient algorithm
                $available = true;
                
                if ($suitcase && $suitcase != 'new') {
                    foreach($suitcase->getItems() as $i) {
                        // We already have this item in our cart;
                        // so we can stop here...
                        if($i->getPackage()->getId() == $package->getId()) {
                            $available = false;
                        }
                    }
                }
                
                $defaultPackages[$package->getParentHeader()]['available'] = $available;
            }
            
            $defaultPackages[$package->getParentHeader()]['new'] = false;
            $defaultPackages[$package->getParentHeader()]['popular'] = false;
            
            $defaultPackages[$package->getParentHeader()]['new'] = $defaultPackages[$package->getParentHeader()]['new'] || $package->getIsNew();
            $defaultPackages[$package->getParentHeader()]['popular'] = $defaultPackages[$package->getParentHeader()]['popular'] || $package->getIsBestSeller();
        }
        
        // Special considerations for Admin users who will see all locales
        // and all "default" versions.  So we want them to see the "default"
        // as the US version (ignoring the defaults in others locales).
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            foreach($packages as $package) {
                if ($package->getIsDefault()) {
                    $origins = $package->getOrigins();
                    $localeArray = array();
                    foreach($origins as $o) {
                        $localeArray[] = $o->getCode();
                    }
                    
                    if (in_array('us', $localeArray)) {
                        $defaultPackages[$package->getParentHeader()]['default'] = $package;
                    }
                }
            }
        }
        
        foreach ($defaultPackages as $key => $item) {
            $defaultPackages[$key]['count'] = count($item['packages']);
            
            // If, for some reason, we didn't set a default package we
            // remove it from the list of packages returned to the template
            if (!isset($defaultPackages[$key]['available'])) {
                unset($defaultPackages[$key]);
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
        $locale = $request->getLocale();
        $q = $request->query->get('q');
        
        $response = new JsonResponse();
        $sphinxSearch = $this->get('search.sphinxsearch.search');
        
        if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $sphinxSearch->setFilter('isprivate', array(1), true);
        }
        
        $sphinxSearch->setMatchMode(SPH_MATCH_EXTENDED);
        $searchResults = $sphinxSearch->search($q . '*', array('Packages'), array(
            'result_limit' => 1000,
            'result_offset' => 0,
            'field_weights' => array(
                'categories' => 4,
                'parentheader' => 2
            )
        ), false);
        
        $matches = array();
        if(isset($searchResults['matches'])) {
            foreach($searchResults['matches'] as $key => $value) {
                $matches[$key] = $key;
            }
        }
        
        if(!empty($matches)) {
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            $qb->andWhere($qb->expr()->in('p.id', $matches));
            $qb->andWhere('p.active = 1 OR p.seasonal = 1');
            $qb->andWhere('p.available = 1');
            $qb->andWhere('p.is_default = 1');
            $qb->innerJoin('p.origins', 'o', 'WITH', 'o.code = \'' . $locale . '\'');
            
            $packages = $qb->getQuery()->getResult();
        }
        else {
            $packages = array();
        }
        
        foreach($packages as $package) {
            $matches[$package->getId()] = array(
                'slug' => $package->getSlug(),
                'title' => $package->getParentHeader(),
                'image' => $package->getThumbnail()
            );
        }
        
        $results = array();
        foreach ($matches as $match) {
            if (isset($match['slug'])) {
                $results[] = array('package' => $match);
            }
        }
        
        return $response->setData(
            array(
                'packages' => array_slice($results, 0, 10),
                'truncated' => count($results) > 10
            )
        );
    }
    
    
    public function siteNavAction()
    {
//        $host = $this->getHost($this->getRequest());
//        $em = $this->getDoctrine()->getManager();
//        $query = $em->createQuery(
//            'SELECT DISTINCT c.id, IDENTITY(c.parent) AS parent_id FROM InertiaWinspireBundle:Package p LEFT JOIN p.origins o LEFT JOIN p.categories c WHERE p.is_private != 1 AND p.active = 1 AND p.available = 1 AND (o.code = :code) ORDER BY c.id'
//        );
//
//        $query->setParameter('code', $host);
//
//        $test = $query->getResult();
//        $blah = array();
//        foreach($test as $row) {
//            $blah[$row['id']] = true;
//            $blah[$row['parent_id']] = true;
//        }
        
        
        $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
        $categoryTree = $repo->childrenHierarchy();
        
        $categoryTree = $categoryTree[0]['__children'];
        
        // Assign categories to columns for the drop-down navigation
        $temp = array();
        foreach($categoryTree as $subtree) {
//            foreach ($subtree['__children'] as $outerkey => $child) {
//                if (!array_key_exists($child['id'], $blah)) {
//                    unset($subtree['__children'][$outerkey]);
//                }
//            }
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
            
            $links1 = get_post_custom_values('hubspot_url', $wpPost1[0]->ID);
            $links2 = get_post_custom_values('hubspot_url', $wpPost2[0]->ID);
            $links3 = get_post_custom_values('hubspot_url', $wpPost3[0]->ID);
            $links4 = get_post_custom_values('hubspot_url', $wpPost4[0]->ID);
            
            if(count($wpPost1) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost1[0]->ID), array(160, 100)); 
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost1[0]->post_title,
                    'link' => (count($links1) > 0) ? $links1[0] : get_permalink($wpPost1[0]->ID),
                    'date' => new \DateTime($wpPost1[0]->post_date)
                );
            }
            
            if(count($wpPost2) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost2[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost2[0]->post_title,
                    'link' => (count($links2) > 0) ? $links2[0] : get_permalink($wpPost2[0]->ID),
                    'date' => new \DateTime($wpPost2[0]->post_date)
                );
            }
            
            if(count($wpPost3) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost3[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost3[0]->post_title,
                    'link' => (count($links3) > 0) ? $links3[0] : get_permalink($wpPost3[0]->ID),
                    'date' => new \DateTime($wpPost3[0]->post_date)
                );
            }
            
            if(count($wpPost4) > 0) {
                $image =  wp_get_attachment_image_src(get_post_thumbnail_id($wpPost4[0]->ID), array(160, 100));
                $posts[] = array(
                    'image' => $image[0],
                    'title' => $wpPost4[0]->post_title,
                    'link' => (count($links4) > 0) ? $links4[0] : get_permalink($wpPost4[0]->ID),
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
}
