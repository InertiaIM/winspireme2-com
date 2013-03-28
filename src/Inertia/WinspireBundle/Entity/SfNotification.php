<?php
namespace Inertia\WinspireBundle\Entity;

use BeSimple\SoapBundle\ServiceDefinition\Annotation as Soap;

class SfNotification
{
    /**
     * @Soap\ComplexType("string")
     */
    public $OrganizationId;
    
    /**
     * @Soap\ComplexType("string")
     */
    public $ActionId;
    
    /**
     * @Soap\ComplexType("string", nillable=true)
     */
    private $SessionId;
    
    /**
     * @Soap\ComplexType("string")
     */
    private $EnterpriseUrl;
    
    /**
     * @Soap\ComplexType("string")
     */
    private $PartnerUrl;
    
    /**
     * @Soap\ComplexType("array")
     */
    private $PartnerUrl;
}