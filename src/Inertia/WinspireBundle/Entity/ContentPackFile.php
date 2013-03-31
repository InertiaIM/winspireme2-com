<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="content_pack_file")
 * @ORM\Entity
 */
class ContentPackFile
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="md5", type="string", length=128)
     */
    private $md5;
    
    /**
     * @ORM\Column(name="name", type="string", length=128)
     */
    private $name;
    
    /**
     * @ORM\Column(name="location", type="string", length=128)
     */
    private $location;
    
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
     * @ORM\ManyToOne(targetEntity="ContentPackVersion", inversedBy="files", cascade={"persist"})
     * @ORM\JoinColumn(name="content_version_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $contentPackVersion;

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
     * Set md5
     *
     * @param string $md5
     * @return ContentPackFile
     */
    public function setMd5($md5)
    {
        $this->md5 = $md5;
    
        return $this;
    }

    /**
     * Get md5
     *
     * @return string 
     */
    public function getMd5()
    {
        return $this->md5;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return ContentPackFile
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
     * Set location
     *
     * @param string $location
     * @return ContentPackFile
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
     * Set created
     *
     * @param \DateTime $created
     * @return ContentPackFile
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
     * @return ContentPackFile
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
     * Set contentVersion
     *
     * @param \Inertia\WinspireBundle\Entity\ContentVersion $contentVersion
     * @return ContentPackFile
     */
    public function setContentVersion(\Inertia\WinspireBundle\Entity\ContentVersion $contentVersion = null)
    {
        $this->contentVersion = $contentVersion;
    
        return $this;
    }

    /**
     * Get contentVersion
     *
     * @return \Inertia\WinspireBundle\Entity\ContentVersion 
     */
    public function getContentVersion()
    {
        return $this->contentVersion;
    }

    /**
     * Set contentPackVersion
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPackVersion $contentPackVersion
     * @return ContentPackFile
     */
    public function setContentPackVersion(\Inertia\WinspireBundle\Entity\ContentPackVersion $contentPackVersion = null)
    {
        $this->contentPackVersion = $contentPackVersion;
    
        return $this;
    }

    /**
     * Get contentPackVersion
     *
     * @return \Inertia\WinspireBundle\Entity\ContentPackVersion 
     */
    public function getContentPackVersion()
    {
        return $this->contentPackVersion;
    }
}