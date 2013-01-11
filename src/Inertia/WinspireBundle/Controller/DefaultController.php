<?php

namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('InertiaWinspireBundle:Default:index.html.twig');
    }
    
    public function packageListAction($slug)
    {
        $session = $this->getRequest()->getSession();
        $suitcase = $session->get('suitcase', array());
        
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
        $query = $em->createQuery(
            'SELECT p, c FROM InertiaWinspireBundle:Package p JOIN p.categories c WHERE c.id IN (:ids) AND p.is_private != 1 AND p.picture IS NOT NULL ORDER BY p.parent_header ASC, p.is_default DESC'
        )->setParameter('ids', $catIds);
        
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
                foreach($suitcase as $p) {
                    // We already have this item in our cart;
                    // so we can stop here...
                    if($p['id'] == $index) {
                        $available = false;
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
        $suitcase = $session->get('suitcase', array());
        
        if ($categories = $request->query->get('category')) {
            $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
            $filterTree = $repo->childrenHierarchy();
            $filterTree = $filterTree[0]['__children'];
            
            $matchedCategories = array();
            foreach($filterTree as $parent) {
                if(array_key_exists($parent['id'], $categories)) {
                    $matchedCategories[$parent['id']][] = $parent['id'];
                    
                    // if a parent category is chosen, then add all child categories
                    if(array_search($parent['id'], $categories[$parent['id']]) !== FALSE) {
                        foreach($parent['__children'] as $child) {
                            $matchedCategories[$parent['id']][] = $child['id'];
                        }
                    }
                    else {
                        foreach($categories[$parent['id']] as $child) {
                            $matchedCategories[$parent['id']][] = $child;
                        }
                    }
                }
            }
            
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            $qb->innerJoin('p.categories', 'c');
            
            $qb->andWhere('p.is_private != 1');
            $qb->andWhere('p.picture IS NOT NULL');
            
            if($request->query->get('sortOrder') == 'alpha-desc') {
                $qb->orderBy('p.parent_header', 'DESC');
            }
            else {
                $qb->orderBy('p.parent_header', 'ASC');
            }
            
            $qb->addOrderBy('p.is_default', 'DESC');
            
            // TODO has to be a way to handle this directly in the queryBuilder
            // This is an intersect operation...
            foreach($matchedCategories as $categoryGroup) {
//            $qb->andWhere($qb->expr()->in('c.id', $categoryGroup));
            }
            
//echo $qb->getQuery()->getSql();
            $packages = $qb->getQuery()->getResult();
            
            
            // TODO has to be a way to handle this directly in the queryBuilder
            // This is an intersect operation...
            $result = array();
            foreach($packages as $package) {
                $cats = $package->getCategories();
                
                $temp = array();
                foreach($cats as $cat) {
                    $temp[] = $cat->getId();
                }
                
                $test = true;
                foreach($matchedCategories as $catGroup) {
                    if(count(array_intersect($catGroup, $temp)) == 0) {
                        $test = false;
                        break;
                    }
                }
                
                if($test) {
                    $result[] = $package;
                }
            }
            $packages = $result;
        }
        else {
            $em = $this->getDoctrine()->getManager();
            $qb = $em->createQueryBuilder();
            
            $qb->select('p')->from('InertiaWinspireBundle:Package', 'p');
            
            $qb->andWhere('p.is_private != 1');
            $qb->andWhere('p.picture IS NOT NULL');
            
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
                foreach($suitcase as $p) {
                    // We already have this item in our cart;
                    // so we can stop here...
                    if($p['id'] == $index) {
                        $available = false;
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
            
            $defaultPackages[$index]['new'] = $defaultPackages[$index]['new'] || $package->getIsNew();
            $defaultPackages[$index]['popular'] = $defaultPackages[$index]['popular'] || $package->getIsBestSeller();
            $defaultPackages[$index]['count'] = $count;
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
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.slug = :slug'
        )->setParameter('slug', $slug);
        
        $package = $query->getResult();
        
        if (!$package) {
            throw $this->createNotFoundException();
        }
        
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.parent_header = :ph ORDER BY p.parent_header ASC, p.is_default DESC'
        )->setParameter('ph', $package[0]->getParentHeader());
        
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
                $defaultPackages[$index] = array('package' => $package, 'count' => 1);
                $defaultPackages[$index]['variants'] = array($package);
            }
            if($currentHeader != $package->getParentHeader()) {
                $currentHeader = $package->getParentHeader();
            }
            else {
                $count++;
                $defaultPackages[$index]['variants'][] = $package;
            }
            
            $defaultPackages[$index]['count'] = $count;
        }
        
        
        return $this->render(
            'InertiaWinspireBundle:Default:packageDetail.html.twig',
            array(
                'package' => $defaultPackages[$slug]['package'],
                'search' => false,
                'slug' => $slug,
                'variants' => $defaultPackages[$slug]['variants']
            )
        );
    }
    
    public function siteNavAction()
    {
        $repo = $this->getDoctrine()->getRepository('InertiaWinspireBundle:Category');
        $categoryTree = $repo->childrenHierarchy();
        
        $categoryTree = $categoryTree[0]['__children'];
        
        // TODO need an algorithm to assign columns automatically?
        $temp = array();
        $temp[0] = array($categoryTree[0], $categoryTree[1], $categoryTree[2]);
        $temp[1] = array($categoryTree[3], $categoryTree[4]);
        $temp[2] = array($categoryTree[5], $categoryTree[6]);
        $temp[3] = array($categoryTree[7], $categoryTree[8], $categoryTree[9], $categoryTree[10], $categoryTree[11]);
        
        return $this->render(
            'InertiaWinspireBundle:Default:siteNav.html.twig',
            array('categories' => $temp)
        );
    }
}
