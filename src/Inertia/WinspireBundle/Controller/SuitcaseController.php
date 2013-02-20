<?php

namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SuitcaseController extends Controller
{
    public function addAction($id)
    {
        $session = $this->getRequest()->getSession();
        $suitcase = $session->get('suitcase', array());
        $response = new JsonResponse();
        
        foreach($suitcase as $p) {
            // We already have this item in our cart;
            // so we can stop here...
            if($p['id'] == $id) {
                return $response;
            }
        }
        
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.id = :id'
        )->setParameter('id', $id);
        
        try {
            $package = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            throw $this->createNotFoundException();
        }
        
        array_unshift ($suitcase, array(
            'id' => $id,
            'slug' => $package->getSlug(),
            'thumb' => $package->getThumbnail(),
            'title' => $package->getParentHeader()
        ));
        
        $session->set('suitcase', $suitcase);
    
    public function buttonWidgetAction()
    {
        $suitcase = $this->getSuitcase();
        
        return $this->render('InertiaWinspireBundle:Suitcase:buttonWidget.html.twig',
            array(
                'suitcase' => $suitcase
            )
        );
    }
    
        
        $response->setData(array(
            'slug' => $package->getSlug(),
            'thumb' => $package->getThumbnail(),
            'title' => $package->getParentHeader(),
            'count' => count($suitcase)
        ));
        
        return $response;
    }
    
    public function previewAction()
    {
        $session = $this->getRequest()->getSession();
        $suitcase = $session->get('suitcase', array());
        
        return $this->render('InertiaWinspireBundle:Suitcase:preview.html.twig', array(
            'suitcase' => $suitcase
        ));
    }
}
