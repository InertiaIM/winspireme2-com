<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PageController extends Controller
{
    public function displayAction($slug)
    {
        if ($this->get('templating')->exists('InertiaWinspireBundle:Page:_' . $slug . '.html.twig')) {
            
        }
        else {
            throw $this->createNotFoundException();
        }
        
        return $this->render('InertiaWinspireBundle:Page:_' . $slug . '.html.twig');
    }
    
    public function noneAction($url)
    {
        throw $this->createNotFoundException();
    }
}