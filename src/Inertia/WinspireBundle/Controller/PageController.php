<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Page;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PageController extends Controller
{
    public function displayAction($slug)
    {
        $locale = strtolower($this->getRequest()->getLocale());
        
        if ($this->get('templating')->exists('InertiaWinspireBundle:Page:_' . $slug . '_' . $locale . '.html.twig')) {
            return $this->render('InertiaWinspireBundle:Page:_' . $slug . '_' . $locale . '.html.twig');
        }
        elseif ($this->get('templating')->exists('InertiaWinspireBundle:Page:_' . $slug . '.html.twig')) {
            return $this->render('InertiaWinspireBundle:Page:_' . $slug . '.html.twig');
        }
        else {
            throw $this->createNotFoundException();
        }
    }
    
    public function noneAction($url)
    {
        throw $this->createNotFoundException();
    }
}