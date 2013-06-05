<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="suitcase")
 * @ORM\Entity
 */
class Suitcase
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="name", type="string", length=255, nullable=true)
     */
    private $name;
    
    /**
     * @ORM\Column(name="status", type="string", length=1, nullable=true)
     */
    private $status;
    
    /**
     * @ORM\Column(name="packed", type="boolean")
     */
    private $packed;
    
    /**
     * @ORM\Column(name="event_name", type="string", length=255, nullable=true)
     */
    private $eventName;
    
    /**
     * @ORM\Column(name="event_date", type="date", nullable=true)
     */
    private $eventDate;
    
    /**
     * @ORM\Column(name="packed_at", type="datetime", nullable=true)
     */
    private $packedAt;
    
    /**
     * @ORM\Column(name="invoice_requested_at", type="datetime", nullable=true)
     */
    private $invoiceRequestedAt;
    
    /**
     * @ORM\Column(name="invoice_provided_at", type="datetime", nullable=true)
     */
    private $invoiceProvidedAt;
    
    /**
     * @ORM\Column(name="invoice_paid_at", type="datetime", nullable=true)
     */
    private $invoicePaidAt;
    
    /**
     * @ORM\Column(name="invoice_file_name", type="string", length=256, nullable=true)
     */
    private $invoiceFileName;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    private $sfId;
    
    /**
     * @ORM\Column(name="sf_role_id", type="string", length=128, nullable=true)
     */
    private $sfContactRoleId;
    
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
     * @ORM\Column(name="dirty", type="boolean", nullable=true)
     */
    private $dirty;
    
    /**
     * @ORM\Column(name="sf_updated", type="datetime", nullable=true)
     */
    private $sfUpdated;
    
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="suitcases")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;
    
    /**
     * @ORM\OneToMany(targetEntity="SuitcaseItem", mappedBy="suitcase")
     */
    protected $items;
    
    /**
     * @ORM\OneToMany(targetEntity="Share", mappedBy="suitcase")
     */
    protected $shares;
    
    /**
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="suitcase")
     */
    protected $comments;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->items = new \Doctrine\Common\Collections\ArrayCollection();
        $this->shares = new \Doctrine\Common\Collections\ArrayCollection();
        $this->comments = new \Doctrine\Common\Collections\ArrayCollection();
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
     * Set name
     *
     * @param string $name
     * @return Suitcase
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
     * Set eventName
     *
     * @param string $eventName
     * @return Suitcase
     */
    public function setEventName($eventName)
    {
        $this->eventName = $eventName;
    
        return $this;
    }

    /**
     * Get eventName
     *
     * @return string 
     */
    public function getEventName()
    {
        return $this->eventName;
    }

    /**
     * Set eventDate
     *
     * @param \DateTime $eventDate
     * @return Suitcase
     */
    public function setEventDate($eventDate)
    {
        $this->eventDate = $eventDate;
    
        return $this;
    }

    /**
     * Get eventDate
     *
     * @return \DateTime 
     */
    public function getEventDate()
    {
        return $this->eventDate;
    }

    /**
     * Set sfId
     *
     * @param string $sfId
     * @return Suitcase
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
     * @return Suitcase
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
     * @return Suitcase
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
     * Set salesperson
     *
     * @param \Inertia\WinspireBundle\Entity\User $salesperson
     * @return Suitcase
     */
    public function setSalesperson(\Inertia\WinspireBundle\Entity\User $salesperson = null)
    {
        $this->salesperson = $salesperson;
    
        return $this;
    }

    /**
     * Get salesperson
     *
     * @return \Inertia\WinspireBundle\Entity\User 
     */
    public function getSalesperson()
    {
        return $this->salesperson;
    }

    /**
     * Set user
     *
     * @param \Inertia\WinspireBundle\Entity\User $user
     * @return Suitcase
     */
    public function setUser(\Inertia\WinspireBundle\Entity\User $user = null)
    {
        $this->user = $user;
    
        return $this;
    }

    /**
     * Get user
     *
     * @return \Inertia\WinspireBundle\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add items
     *
     * @param \Inertia\WinspireBundle\Entity\SuitcaseItem $items
     * @return Suitcase
     */
    public function addItem(\Inertia\WinspireBundle\Entity\SuitcaseItem $items)
    {
        $items->setSuitcase($this);
        $this->items[] = $items;
    
        return $this;
    }

    /**
     * Remove items
     *
     * @param \Inertia\WinspireBundle\Entity\SuitcaseItem $items
     */
    public function removeItem(\Inertia\WinspireBundle\Entity\SuitcaseItem $items)
    {
        $this->items->removeElement($items);
    }

    /**
     * Get items
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set packed
     *
     * @param boolean $packed
     * @return Suitcase
     */
    public function setPacked($packed)
    {
        $this->packed = $packed;
    
        return $this;
    }

    /**
     * Get packed
     *
     * @return boolean 
     */
    public function getPacked()
    {
        return $this->packed;
    }

    /**
     * Set packedAt
     *
     * @param \DateTime $packedAt
     * @return Suitcase
     */
    public function setPackedAt($packedAt)
    {
        $this->packedAt = $packedAt;
    
        return $this;
    }

    /**
     * Get packedAt
     *
     * @return \DateTime 
     */
    public function getPackedAt()
    {
        return $this->packedAt;
    }

    /**
     * Add shares
     *
     * @param \Inertia\WinspireBundle\Entity\Share $shares
     * @return Suitcase
     */
    public function addShare(\Inertia\WinspireBundle\Entity\Share $shares)
    {
        $shares->setSuitcase($this);
        $this->shares[] = $shares;
        
        return $this;
    }

    /**
     * Remove shares
     *
     * @param \Inertia\WinspireBundle\Entity\Share $shares
     */
    public function removeShare(\Inertia\WinspireBundle\Entity\Share $shares)
    {
        $this->shares->removeElement($shares);
    }

    /**
     * Get shares
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getShares()
    {
        return $this->shares;
    }

    /**
     * Add comments
     *
     * @param \Inertia\WinspireBundle\Entity\Comment $comments
     * @return Suitcase
     */
    public function addComment(\Inertia\WinspireBundle\Entity\Comment $comments)
    {
        $this->comments[] = $comments;
    
        return $this;
    }

    /**
     * Remove comments
     *
     * @param \Inertia\WinspireBundle\Entity\Comment $comments
     */
    public function removeComment(\Inertia\WinspireBundle\Entity\Comment $comments)
    {
        $this->comments->removeElement($comments);
    }

    /**
     * Get comments
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getComments()
    {
        return $this->comments;
    }

    /**
     * Set sfUpdated
     *
     * @param \DateTime $sfUpdated
     * @return Suitcase
     */
    public function setSfUpdated($sfUpdated)
    {
        $this->sfUpdated = $sfUpdated;
    
        return $this;
    }

    /**
     * Get sfUpdated
     *
     * @return \DateTime 
     */
    public function getSfUpdated()
    {
        return $this->sfUpdated;
    }

    /**
     * Set dirty
     *
     * @param boolean $dirty
     * @return Suitcase
     */
    public function setDirty($dirty)
    {
        $this->dirty = $dirty;
    
        return $this;
    }

    /**
     * Get dirty
     *
     * @return boolean 
     */
    public function getDirty()
    {
        return $this->dirty;
    }

    /**
     * Set invoiceRequested
     *
     * @param boolean $invoiceRequested
     * @return Suitcase
     */
    public function setInvoiceRequested($invoiceRequested)
    {
        $this->invoiceRequested = $invoiceRequested;
    
        return $this;
    }

    /**
     * Get invoiceRequested
     *
     * @return boolean 
     */
    public function getInvoiceRequested()
    {
        return $this->invoiceRequested;
    }

    /**
     * Set invoicePaid
     *
     * @param boolean $invoicePaid
     * @return Suitcase
     */
    public function setInvoicePaid($invoicePaid)
    {
        $this->invoicePaid = $invoicePaid;
    
        return $this;
    }

    /**
     * Get invoicePaid
     *
     * @return boolean 
     */
    public function getInvoicePaid()
    {
        return $this->invoicePaid;
    }

    /**
     * Set invoiceRequestedAt
     *
     * @param \DateTime $invoiceRequestedAt
     * @return Suitcase
     */
    public function setInvoiceRequestedAt($invoiceRequestedAt)
    {
        $this->invoiceRequestedAt = $invoiceRequestedAt;
    
        return $this;
    }

    /**
     * Get invoiceRequestedAt
     *
     * @return \DateTime 
     */
    public function getInvoiceRequestedAt()
    {
        return $this->invoiceRequestedAt;
    }

    /**
     * Set invoicePaidAt
     *
     * @param \DateTime $invoicePaidAt
     * @return Suitcase
     */
    public function setInvoicePaidAt($invoicePaidAt)
    {
        $this->invoicePaidAt = $invoicePaidAt;
    
        return $this;
    }

    /**
     * Get invoicePaidAt
     *
     * @return \DateTime 
     */
    public function getInvoicePaidAt()
    {
        return $this->invoicePaidAt;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return Suitcase
     */
    public function setStatus($status)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return string 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set sfContactRoleId
     *
     * @param string $sfContactRoleId
     * @return Suitcase
     */
    public function setSfContactRoleId($sfContactRoleId)
    {
        $this->sfContactRoleId = $sfContactRoleId;
    
        return $this;
    }

    /**
     * Get sfContactRoleId
     *
     * @return string 
     */
    public function getSfContactRoleId()
    {
        return $this->sfContactRoleId;
    }

    /**
     * Set invoiceProvidedAt
     *
     * @param \DateTime $invoiceProvidedAt
     * @return Suitcase
     */
    public function setInvoiceProvidedAt($invoiceProvidedAt)
    {
        $this->invoiceProvidedAt = $invoiceProvidedAt;
    
        return $this;
    }

    /**
     * Get invoiceProvidedAt
     *
     * @return \DateTime 
     */
    public function getInvoiceProvidedAt()
    {
        return $this->invoiceProvidedAt;
    }

    /**
     * Set invoiceFileName
     *
     * @param string $invoiceFileName
     * @return Suitcase
     */
    public function setInvoiceFileName($invoiceFileName)
    {
        $this->invoiceFileName = $invoiceFileName;
    
        return $this;
    }

    /**
     * Get invoiceFileName
     *
     * @return string 
     */
    public function getInvoiceFileName()
    {
        return $this->invoiceFileName;
    }
}