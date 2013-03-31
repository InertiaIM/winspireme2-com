<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="content_pack")
 * @ORM\Entity
 */
class ContentPack
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="sf_title", type="string", length=256, nullable=true)
     */
    private $sfTitle;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    
    private $sfId;
    
    /**
     * @ORM\Column(name="latest_sf_version_id", type="string", length=128, nullable=true)
     */
    
    private $latestSfVersionId;
    
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
     * @ORM\OneToMany(targetEntity="ContentPackVersion", mappedBy="contentPack", cascade={"persist"})
     */
    protected $versions;
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->versions = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
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
     * Set title
     *
     * @param string $title
     * @return ContentPack
     */
    public function setTitle($title)
    {
        $this->title = $title;
    
        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set sfId
     *
     * @param string $sfId
     * @return ContentPack
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
     * Set latestSfVersionId
     *
     * @param string $latestSfVersionId
     * @return ContentPack
     */
    public function setLatestSfVersionId($latestSfVersionId)
    {
        $this->latestSfVersionId = $latestSfVersionId;
    
        return $this;
    }

    /**
     * Get latestSfVersionId
     *
     * @return string 
     */
    public function getLatestSfVersionId()
    {
        return $this->latestSfVersionId;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return ContentPack
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
     * @return ContentPack
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
     * Add versions
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPackVersion $versions
     * @return ContentPack
     */
    public function addVersion(\Inertia\WinspireBundle\Entity\ContentPackVersion $versions)
    {
        $versions->setContentPack($this);
        $this->versions[] = $versions;
    
        return $this;
    }

    /**
     * Remove versions
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPackVersion $versions
     */
    public function removeVersion(\Inertia\WinspireBundle\Entity\ContentPackVersion $versions)
    {
        $this->versions->removeElement($versions);
    }

    /**
     * Get versions
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getVersions()
    {
        return $this->versions;
    }

    /**
     * Set sfTitle
     *
     * @param string $sfTitle
     * @return ContentPack
     */
    public function setSfTitle($sfTitle)
    {
        $this->sfTitle = $sfTitle;
    
        return $this;
    }

    /**
     * Get sfTitle
     *
     * @return string 
     */
    public function getSfTitle()
    {
        return $this->sfTitle;
    }
}