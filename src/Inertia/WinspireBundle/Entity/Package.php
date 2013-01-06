<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="package")
 * @ORM\Entity
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
     * @ORM\Column(name="parentHeader", type="string", length=256)
     */
    private $parent_header;
    
    /**
     * @ORM\Column(name="slug", type="string", length=256, nullable=true)
     */
    private $slug;
    
    /**
     * @ORM\Column(name="code", type="string", length=128)
     */
    private $code;
    
    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private $description;
    
    /**
     * @ORM\Column(name="location", type="string", length=256, nullable=true)
     */
    private $location;
    
    /**
     * @ORM\Column(name="picture", type="string", length=256, nullable=true)
     */
    private $picture;
    
    /**
     * @ORM\Column(name="thumbnail", type="string", length=256, nullable=true)
     */
    private $thumbnail;
    
    /**
     * @ORM\Column(name="yearVersion", type="string", length=256)
     */
    private $year_version;
    
    /**
     * @ORM\Column(name="classVersion", type="string", length=256, nullable=true)
     */
    private $class_version;
    
    /**
     * @ORM\Column(name="suggestedRetailValue", type="string", length=256, nullable=true)
     */
    private $suggested_retail_value;
    
    /**
     * @ORM\Column(name="cost", type="float", nullable=true)
     */
    private $cost;
    
    /**
     * @ORM\Column(name="isOnHome", type="boolean")
     */
    private $is_on_home;
    
    /**
     * @ORM\Column(name="details", type="text", nullable=true)
     */
    private $details;
    
    /**
     * @ORM\Column(name="more_details", type="text", nullable=true)
     */
    private $moreDetails;
    
    /**
     * @ORM\Column(name="isNew", type="boolean")
     */
    private $is_new;
    
    /**
     * @ORM\Column(name="isBestSeller", type="boolean")
     */
    private $is_best_seller;
    
    /**
     * @ORM\Column(name="isDefault", type="boolean")
     */
    private $is_default;
    
    /**
     * @ORM\Column(name="isPrivate", type="boolean")
     */
    private $is_private;
    
    /**
     * @ORM\Column(name="airfares", type="integer", nullable=true)
     */
    private $airfares;
    
    /**
     * @ORM\Column(name="persons", type="integer", nullable=true)
     */
    private $persons;
    
    /**
     * @ORM\Column(name="accommodations", type="integer", nullable=true)
     */
    private $accommodations;
    
    /**
     * @ORM\Column(name="sf_pricebook_entry_id", type="string", length=128)
     */
    private $sfPricebookEntryId;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128)
     */
    private $sfId;
    
    /**
    * @ORM\ManyToMany(targetEntity="Category", inversedBy="packages")
    * @ORM\JoinTable(name="packages_categories")
    */
    private $categories;
    
    
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
     * Set name
     *
     * @param string $name
     * @return Package
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Package
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    
        return $this;
    }

    /**
     * Get slug
     *
     * @return string 
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return Package
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
     * Set description
     *
     * @param string $description
     * @return Package
     */
    public function setDescription($description)
    {
        $this->description = $description;
    
        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set location
     *
     * @param string $location
     * @return Package
     */
    public function setLocation($location)
    {
        $this->location = $location;
    
        return $this;
    }

    /**
     * Get location
     *
     * @return string 
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * Set picture
     *
     * @param string $picture
     * @return Package
     */
    public function setPicture($picture)
    {
        $this->picture = $picture;
    
        return $this;
    }

    /**
     * Get picture
     *
     * @return string 
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * Set year_version
     *
     * @param string $yearVersion
     * @return Package
     */
    public function setYearVersion($yearVersion)
    {
        $this->year_version = $yearVersion;
    
        return $this;
    }

    /**
     * Get year_version
     *
     * @return string 
     */
    public function getYearVersion()
    {
        return $this->year_version;
    }

    /**
     * Set class_version
     *
     * @param string $classVersion
     * @return Package
     */
    public function setClassVersion($classVersion)
    {
        $this->class_version = $classVersion;
    
        return $this;
    }

    /**
     * Get class_version
     *
     * @return string 
     */
    public function getClassVersion()
    {
        return $this->class_version;
    }

    /**
     * Set suggested_retail_value
     *
     * @param string $suggestedRetailValue
     * @return Package
     */
    public function setSuggestedRetailValue($suggestedRetailValue)
    {
        $this->suggested_retail_value = $suggestedRetailValue;
    
        return $this;
    }

    /**
     * Get suggested_retail_value
     *
     * @return string 
     */
    public function getSuggestedRetailValue()
    {
        return $this->suggested_retail_value;
    }

    /**
     * Set is_on_home
     *
     * @param boolean $isOnHome
     * @return Package
     */
    public function setIsOnHome($isOnHome)
    {
        $this->is_on_home = $isOnHome;
    
        return $this;
    }

    /**
     * Get is_on_home
     *
     * @return boolean 
     */
    public function getIsOnHome()
    {
        return $this->is_on_home;
    }

    /**
     * Set details
     *
     * @param string $details
     * @return Package
     */
    public function setDetails($details)
    {
        $this->details = $details;
    
        return $this;
    }

    /**
     * Get details
     *
     * @return string 
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Set is_new
     *
     * @param boolean $isNew
     * @return Package
     */
    public function setIsNew($isNew)
    {
        $this->is_new = $isNew;
    
        return $this;
    }

    /**
     * Get is_new
     *
     * @return boolean 
     */
    public function getIsNew()
    {
        return $this->is_new;
    }

    /**
     * Set is_best_seller
     *
     * @param boolean $isBestSeller
     * @return Package
     */
    public function setIsBestSeller($isBestSeller)
    {
        $this->is_best_seller = $isBestSeller;
    
        return $this;
    }

    /**
     * Get is_best_seller
     *
     * @return boolean 
     */
    public function getIsBestSeller()
    {
        return $this->is_best_seller;
    }

    /**
     * Set sfId
     *
     * @param string $sfId
     * @return Package
     */
    public function setSfId($sfId)
    {
        $this->sfId = $sfId;
    
        return $this;
    }

    /**
     * Get sfId
     *
     * @return string 
     */
    public function getSfId()
    {
        return $this->sfId;
    }

    /**
     * Set parent_header
     *
     * @param string $parentHeader
     * @return Package
     */
    public function setParentHeader($parentHeader)
    {
        $this->parent_header = $parentHeader;
    
        return $this;
    }

    /**
     * Get parent_header
     *
     * @return string 
     */
    public function getParentHeader()
    {
        return $this->parent_header;
    }

    /**
     * Set cost
     *
     * @param string $cost
     * @return Package
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
    
        return $this;
    }

    /**
     * Get cost
     *
     * @return string 
     */
    public function getCost()
    {
        return $this->cost;
    }

    /**
     * Set is_default
     *
     * @param boolean $isDefault
     * @return Package
     */
    public function setIsDefault($isDefault)
    {
        $this->is_default = $isDefault;
    
        return $this;
    }

    /**
     * Get is_default
     *
     * @return boolean 
     */
    public function getIsDefault()
    {
        return $this->is_default;
    }

    /**
     * Set is_private
     *
     * @param boolean $isPrivate
     * @return Package
     */
    public function setIsPrivate($isPrivate)
    {
        $this->is_private = $isPrivate;
    
        return $this;
    }

    /**
     * Get is_private
     *
     * @return boolean 
     */
    public function getIsPrivate()
    {
        return $this->is_private;
    }

    /**
     * Set airfares
     *
     * @param integer $airfares
     * @return Package
     */
    public function setAirfares($airfares)
    {
        $this->airfares = $airfares;
    
        return $this;
    }

    /**
     * Get airfares
     *
     * @return integer 
     */
    public function getAirfares()
    {
        return $this->airfares;
    }

    /**
     * Set persons
     *
     * @param integer $persons
     * @return Package
     */
    public function setPersons($persons)
    {
        $this->persons = $persons;
    
        return $this;
    }

    /**
     * Get persons
     *
     * @return integer 
     */
    public function getPersons()
    {
        return $this->persons;
    }

    /**
     * Set accommodations
     *
     * @param integer $accommodations
     * @return Package
     */
    public function setAccommodations($accommodations)
    {
        $this->accommodations = $accommodations;
    
        return $this;
    }

    /**
     * Get accommodations
     *
     * @return integer 
     */
    public function getAccommodations()
    {
        return $this->accommodations;
    }

    /**
     * Set sfPricebookEntryId
     *
     * @param string $sfPricebookEntryId
     * @return Package
     */
    public function setSfPricebookEntryId($sfPricebookEntryId)
    {
        $this->sfPricebookEntryId = $sfPricebookEntryId;
    
        return $this;
    }

    /**
     * Get sfPricebookEntryId
     *
     * @return string 
     */
    public function getSfPricebookEntryId()
    {
        return $this->sfPricebookEntryId;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->categories = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add categories
     *
     * @param \Inertia\WinspireBundle\Entity\Category $category
     * @return Package
     */
    public function addCategory(\Inertia\WinspireBundle\Entity\Category $category)
    {
        $this->categories[] = $category;
    
        return $this;
    }

    /**
     * Remove categories
     *
     * @param \Inertia\WinspireBundle\Entity\Category $category
     */
    public function removeCategory(\Inertia\WinspireBundle\Entity\Category $category)
    {
        $this->categories->removeElement($category);
    }

    /**
     * Get categories
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Add categories
     *
     * @param \Inertia\WinspireBundle\Entity\Category $categories
     * @return Package
     */
    public function addCategorie(\Inertia\WinspireBundle\Entity\Category $categories)
    {
        $this->categories[] = $categories;
    
        return $this;
    }

    /**
     * Remove categories
     *
     * @param \Inertia\WinspireBundle\Entity\Category $categories
     */
    public function removeCategorie(\Inertia\WinspireBundle\Entity\Category $categories)
    {
        $this->categories->removeElement($categories);
    }

    /**
     * Set thumbnail
     *
     * @param string $thumbnail
     * @return Package
     */
    public function setThumbnail($thumbnail)
    {
        $this->thumbnail = $thumbnail;
    
        return $this;
    }

    /**
     * Get thumbnail
     *
     * @return string 
     */
    public function getThumbnail()
    {
        return $this->thumbnail;
    }

    /**
     * Set moreDetails
     *
     * @param string $moreDetails
     * @return Package
     */
    public function setMoreDetails($moreDetails)
    {
        $this->moreDetails = $moreDetails;
    
        return $this;
    }

    /**
     * Get moreDetails
     *
     * @return string 
     */
    public function getMoreDetails()
    {
        return $this->moreDetails;
    }
}