<?php
namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class TestController extends Controller
{
    public function testAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/wtf.wsdl');
        $server->setObject($this->get('test_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
}