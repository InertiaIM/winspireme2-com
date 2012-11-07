<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="package")
 */
class Package
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="name", type="string", length=256)
     */
    private $name;
    
    /**
     * @ORM\Column(name="slug", type="string", length=256)
     */
    private $slug;
    
    /**
     * @ORM\Column(name="code", type="string", length=128)
     */
    private $code;
    
    /**
     * @ORM\Column(name="description", type="text")
     */
    private $description;
    
    /**
     * @ORM\Column(name="location", type="string", length=256)
     */
    private $location;
    
    /**
     * @ORM\Column(name="picture", type="string", length=256)
     */
    private $picture;
    
    /**
     * @ORM\Column(name="yearVersion", type="string", length=256)
     */
    private $year_version;
    
    /**
     * @ORM\Column(name="classVersion", type="string", length=256)
     */
    private $class_version;
    
    /**
     * @ORM\Column(name="suggestedRetailValue", type="string", length=256)
     */
    private $suggested_retail_value;
    
    /**
     * @ORM\Column(name="isOnHome", type="boolean")
     */
    private $is_on_home;
    
    /**
     * @ORM\Column(name="details", type="text")
     */
    private $details;
    
    /**
     * @ORM\Column(name="isNew", type="boolean")
     */
    private $is_new;
    
    /**
     * @ORM\Column(name="isBestSeller", type="boolean")
     */
    private $is_best_seller;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128)
     */
    private $sfId;
}