<?php
namespace Inertia\WinspireBundle\Entity;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;

class SfResponse
{
    /**
     * @Soap\ComplexType("boolean")
     */
    public $Ack;
}