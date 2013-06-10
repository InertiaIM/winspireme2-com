<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PageController extends Controller
{
    public function displayAction($slug)
    {
        $em = $this->getDoctrine()->getManager();
        
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Page p WHERE p.slug = :slug'
        )
        ->setParameter('slug', $slug);
        
        try {
            $page = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            throw $this->createNotFoundException();
        }
        
        if ($slug == 'redeem' || $slug == 'redeem-luxe' || $slug == 'redeem-winspire' || $slug == 'redeem-thanks') {
            return $this->render('InertiaWinspireBundle:Page:display2.html.twig', array(
                'page' => $page
            ));
        }
        else {
            return $this->render('InertiaWinspireBundle:Page:display.html.twig', array(
                'page' => $page
            ));
        }
    }
}