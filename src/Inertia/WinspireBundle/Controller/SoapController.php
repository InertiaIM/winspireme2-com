<?php
namespace Inertia\WinspireBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class SoapController extends Controller
{
    public function accountAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/accountNotifications.wsdl.xml');
        $server->setObject($this->get('account_soap_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
    
    public function bookingAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/salesforce/bookingNotifications.wsdl.xml');
        $server->setObject($this->get('winspire.soap.booking_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
    
    public function contactAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/contactNotifications.wsdl.xml');
        $server->setObject($this->get('contact_soap_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
    
    public function invoiceAction()
    {
        // Not an actual SOAP service, but this seems like the best place to keep
        // another action for a SF web service callout.
        
        $server = $this->get('winspire.rest.invoice_service');
        
        $response = new Response();
//        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        $result = $server->handle($this->getRequest());
        
        return $response;
    }
    
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
    
    public function suitcaseAction()
    {
        $server = new \SoapServer(__DIR__ . '/../../../../app/config/suitcaseNotifications.wsdl.xml');
        $server->setObject($this->get('suitcase_soap_service'));
        
        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=ISO-8859-1');
        
        ob_start();
        $server->handle();
        $response->setContent(ob_get_clean());
        
        return $response;
    }
}