<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

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
     * @ORM\Column(name="keywords", type="string", length=256, nullable=true)
     */
    private $keywords;
    
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
     * @ORM\Column(name="picture_title", type="string", length=256, nullable=true)
     */
    private $pictureTitle;
    
    /**
     * @ORM\Column(name="yearVersion", type="string", length=256, nullable=true)
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
    
//    /**
//     * @ORM\Column(name="recommendations", type="string", length=256, nullable=true)
//     */
//    private $recommendations;
    
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
     * @ORM\Column(name="seasonal", type="boolean", nullable=true)
     */
    private $seasonal;
    
    /**
     * @ORM\Column(name="active", type="boolean", nullable=true)
     */
    private $active;
    
    /**
     * @ORM\Column(name="available", type="boolean", nullable=true)
     */
    private $available;
    
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
     * @ORM\Column(name="content_pack", type="string", length=256, nullable=true)
     */
    private $contentPack;
    
    /**
     * @ORM\Column(name="sf_content_pack_id", type="string", length=256, nullable=true)
     */
    private $sfContentPackId;
    
    /**
     * @ORM\Column(name="meta_title", type="string", length=256, nullable=true)
     */
    private $metaTitle;
    
    /**
     * @ORM\Column(name="meta_description", type="string", length=256, nullable=true)
     */
    private $metaDescription;
    
    /**
     * @ORM\Column(name="meta_keywords", type="string", length=256, nullable=true)
     */
    private $metaKeywords;
    
    /**
     * @ORM\Column(name="sf_pricebook_entry_id", type="string", length=128)
     */
    private $sfPricebookEntryId;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128)
     */
    private $sfId;
    
    /**
     * @var datetime $created
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime")
     */
    private $created;
    
    /**
     * @var datetime $updated
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime")
     */
    private $updated;
    
    /**
    * @ORM\ManyToMany(targetEntity="Category", inversedBy="packages")
    * @ORM\JoinTable(name="packages_categories")
    */
    private $categories;
    
    /**
     * @ORM\OneToMany(targetEntity="SuitcaseItem", mappedBy="package")
     */
    protected $suitcaseItems;
    
    /**
     * @ORM\ManyToMany(targetEntity="Package", mappedBy="recommendations")
     **/
    private $recommendedBy;
    
    /**
     * @ORM\ManyToMany(targetEntity="Package", inversedBy="recommendedBy")
     * @ORM\JoinTable(name="package_recommendations",
     *     joinColumns={@ORM\JoinColumn(name="package_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="recommended_package_id", referencedColumnName="id")}
     * )
     **/
    private $recommendations;
    
    
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
        $this->recommendedBy = new \Doctrine\Common\Collections\ArrayCollection();
        $this->recommendations = new \Doctrine\Common\Collections\ArrayCollection();
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

    /**
     * Add suitcaseItems
     *
     * @param \Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItems
     * @return Package
     */
    public function addSuitcaseItem(\Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItems)
    {
        $this->suitcaseItems[] = $suitcaseItems;
    
        return $this;
    }

    /**
     * Remove suitcaseItems
     *
     * @param \Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItems
     */
    public function removeSuitcaseItem(\Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItems)
    {
        $this->suitcaseItems->removeElement($suitcaseItems);
    }

    /**
     * Get suitcaseItems
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSuitcaseItems()
    {
        return $this->suitcaseItems;
    }

    /**
     * Set keywords
     *
     * @param string $keywords
     * @return Package
     */
    public function setKeywords($keywords)
    {
        $this->keywords = $keywords;
    
        return $this;
    }

    /**
     * Get keywords
     *
     * @return string 
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * Set pictureTitle
     *
     * @param string $pictureTitle
     * @return Package
     */
    public function setPictureTitle($pictureTitle)
    {
        $this->pictureTitle = $pictureTitle;
    
        return $this;
    }

    /**
     * Get pictureTitle
     *
     * @return string 
     */
    public function getPictureTitle()
    {
        return $this->pictureTitle;
    }

    /**
     * Set seasonal
     *
     * @param boolean $seasonal
     * @return Package
     */
    public function setSeasonal($seasonal)
    {
        $this->seasonal = $seasonal;
    
        return $this;
    }

    /**
     * Get seasonal
     *
     * @return boolean 
     */
    public function getSeasonal()
    {
        return $this->seasonal;
    }

    /**
     * Set contentPack
     *
     * @param string $contentPack
     * @return Package
     */
    public function setContentPack($contentPack)
    {
        $this->contentPack = $contentPack;
    
        return $this;
    }

    /**
     * Get contentPack
     *
     * @return string 
     */
    public function getContentPack()
    {
        return $this->contentPack;
    }

    /**
     * Set metaTitle
     *
     * @param string $metaTitle
     * @return Package
     */
    public function setMetaTitle($metaTitle)
    {
        $this->metaTitle = $metaTitle;
    
        return $this;
    }

    /**
     * Get metaTitle
     *
     * @return string 
     */
    public function getMetaTitle()
    {
        return $this->metaTitle;
    }

    /**
     * Set metaDescription
     *
     * @param string $metaDescription
     * @return Package
     */
    public function setMetaDescription($metaDescription)
    {
        $this->metaDescription = $metaDescription;
    
        return $this;
    }

    /**
     * Get metaDescription
     *
     * @return string 
     */
    public function getMetaDescription()
    {
        return $this->metaDescription;
    }

    /**
     * Set metaKeywords
     *
     * @param string $metaKeywords
     * @return Package
     */
    public function setMetaKeywords($metaKeywords)
    {
        $this->metaKeywords = $metaKeywords;
    
        return $this;
    }

    /**
     * Get metaKeywords
     *
     * @return string 
     */
    public function getMetaKeywords()
    {
        return $this->metaKeywords;
    }

    /**
     * Set sfContentPackId
     *
     * @param string $sfContentPackId
     * @return Package
     */
    public function setSfContentPackId($sfContentPackId)
    {
        $this->sfContentPackId = $sfContentPackId;
    
        return $this;
    }

    /**
     * Get sfContentPackId
     *
     * @return string 
     */
    public function getSfContentPackId()
    {
        return $this->sfContentPackId;
    }

    /**
     * Set active
     *
     * @param boolean $active
     * @return Package
     */
    public function setActive($active)
    {
        $this->active = $active;
    
        return $this;
    }

    /**
     * Get active
     *
     * @return boolean 
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Package
     */
    public function setCreated($created)
    {
        $this->created = $created;
    
        return $this;
    }

    /**
     * Get created
     *
     * @return \DateTime 
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Set updated
     *
     * @param \DateTime $updated
     * @return Package
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated;
    
        return $this;
    }

    /**
     * Get updated
     *
     * @return \DateTime 
     */
    public function getUpdated()
    {
        return $this->updated;
    }

    /**
     * Add recommendedBy
     *
     * @param \Inertia\WinspireBundle\Entity\Package $recommendedBy
     * @return Package
     */
    public function addRecommendedBy(\Inertia\WinspireBundle\Entity\Package $recommendedBy)
    {
        $this->recommendedBy[] = $recommendedBy;
    
        return $this;
    }

    /**
     * Remove recommendedBy
     *
     * @param \Inertia\WinspireBundle\Entity\Package $recommendedBy
     */
    public function removeRecommendedBy(\Inertia\WinspireBundle\Entity\Package $recommendedBy)
    {
        $this->recommendedBy->removeElement($recommendedBy);
    }

    /**
     * Get recommendedBy
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRecommendedBy()
    {
        return $this->recommendedBy;
    }

    /**
     * Add recommendations
     *
     * @param \Inertia\WinspireBundle\Entity\Package $recommendations
     * @return Package
     */
    public function addRecommendation(\Inertia\WinspireBundle\Entity\Package $recommendations)
    {
        $this->recommendations[] = $recommendations;
    
        return $this;
    }

    /**
     * Remove recommendations
     *
     * @param \Inertia\WinspireBundle\Entity\Package $recommendations
     */
    public function removeRecommendation(\Inertia\WinspireBundle\Entity\Package $recommendations)
    {
        $this->recommendations->removeElement($recommendations);
    }

    /**
     * Get recommendations
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRecommendations()
    {
        return $this->recommendations;
    }

    /**
     * Set available
     *
     * @param boolean $available
     * @return Package
     */
    public function setAvailable($available)
    {
        $this->available = $available;
    
        return $this;
    }

    /**
     * Get available
     *
     * @return boolean 
     */
    public function getAvailable()
    {
        return $this->available;
    }

}