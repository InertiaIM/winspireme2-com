<?php
namespace Inertia\WinspireBundle\Controller;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;
use Symfony\Component\DependencyInjection\ContainerAware;

class SoapController extends ContainerAware
{
    /**
     * @Soap\Method("notifications")
     * @Soap\Param("request", phpType = "Inertia\WinspireBundle\Entity\SfNotification")
     * @Soap\Result(phpType = "Inertia\WinspireBundle\Entity\SfResponse")
     */
    public function notificationsAction($request)
    {
        $logger = $this->get('logger');
        $logger->info('It\'s big, it\'s heavy, it\'s wood');
        
        
        
        return true;
//        return $this->container->get('besimple.soap.response')->setReturnValue(sprintf('Goodbye %s!', $name));
    }
}
