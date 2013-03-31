<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="content_pack_version")
 * @ORM\Entity
 */
class ContentPackVersion
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
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
     * @ORM\ManyToOne(targetEntity="ContentPack", inversedBy="versions", cascade={"persist"})
     * @ORM\JoinColumn(name="content_pack_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $contentPack;
    
    /**
     * @ORM\OneToMany(targetEntity="ContentPackFile", mappedBy="contentPackVersion", cascade={"persist"})
     */
    protected $files;
    
    
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
     * Set sfId
     *
     * @param string $sfId
     * @return ContentPackVersion
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
     * Set created
     *
     * @param \DateTime $created
     * @return ContentPackVersion
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
     * @return ContentPackVersion
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
     * Set contentPack
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPack $contentPack
     * @return ContentPackVersion
     */
    public function setContentPack(\Inertia\WinspireBundle\Entity\ContentPack $contentPack = null)
    {
        $this->contentPack = $contentPack;
    
        return $this;
    }

    /**
     * Get contentPack
     *
     * @return \Inertia\WinspireBundle\Entity\ContentPack 
     */
    public function getContentPack()
    {
        return $this->contentPack;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->files = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add files
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPackFile $files
     * @return ContentPackVersion
     */
    public function addFile(\Inertia\WinspireBundle\Entity\ContentPackFile $files)
    {
        $files->setContentPackVersion($this);
        $this->files[] = $files;
    
        return $this;
    }

    /**
     * Remove files
     *
     * @param \Inertia\WinspireBundle\Entity\ContentPackFile $files
     */
    public function removeFile(\Inertia\WinspireBundle\Entity\ContentPackFile $files)
    {
        $this->files->removeElement($files);
    }

    /**
     * Get files
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getFiles()
    {
        return $this->files;
    }
}