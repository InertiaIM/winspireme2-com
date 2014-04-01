<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="package_origin")
 * @ORM\Entity
 */
class PackageOrigin
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="code", type="string", length=256)
     */
    private $code;
    
    /**
     * @ORM\ManyToOne(targetEntity="Package", inversedBy="origins")
     * @ORM\JoinColumn(name="package_id", referencedColumnName="id", onDelete="CASCADE")
     **/
    private $package;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return PackageOrigin
     */
    public function setCode($code)
    {
        $this->code = $code;
    
        return $this;
    }

    /**
     * Get code
     *
     * @return string 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set package
     *
     * @param \Inertia\WinspireBundle\Entity\Package $package
     * @return PackageOrigin
     */
    public function setPackage(\Inertia\WinspireBundle\Entity\Package $package = null)
    {
        $this->package = $package;
    
        return $this;
    }

    /**
     * Get package
     *
     * @return \Inertia\WinspireBundle\Entity\Package 
     */
    public function getPackage()
    {
        return $this->package;
    }
}