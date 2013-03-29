<?php
namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class SoapController extends Controller
{
    public function packageAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/packageNotifications.wsdl.xml');
        $server->setObject($this->get('package_soap_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
}