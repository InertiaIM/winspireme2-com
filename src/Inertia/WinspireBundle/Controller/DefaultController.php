<?php

namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('InertiaWinspireBundle:Default:index.html.twig');
    }
    
    public function packageListAction()
    {
        return $this->render('InertiaWinspireBundle:Default:packageList.html.twig');
    }
    
    public function packageDetailAction()
    {
        return $this->render('InertiaWinspireBundle:Default:packageDetail.html.twig');
    }
    
    public function siteNavAction()
    {
        return $this->render(
                'InertiaWinspireBundle:Default:siteNav.html.twig',
                array('categories' => null)
        );
    }
}
