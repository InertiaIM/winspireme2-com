<?php
namespace Inertia\WinspireBundle\Controller;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;
use Symfony\Component\DependencyInjection\ContainerAware;

class SoapController extends ContainerAware
{
    /**
     * @Soap\Method("package")
     * @Soap\Param("id", phpType = "string")
     * @Soap\Result(phpType = "string")
     */
    public function packageAction($id)
    {
        return sprintf('Hello!');
//        return $this->container->get('besimple.soap.response')->setReturnValue(sprintf('Goodbye %s!', $name));
    }
}
