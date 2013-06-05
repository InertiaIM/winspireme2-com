<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Inertia\WinspireBundle\Entity\Share
 *
 * @ORM\Table(name="booking")
 * @ORM\Entity
 */
class Booking
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     * @ORM\Column(name="first_name", type="string", length=128, nullable=true)
     */
    private $firstName;
    
    /**
     * @ORM\Column(name="last_name", type="string", length=128, nullable=true)
     */
    private $lastName;
    
    /**
     * @ORM\Column(name="email", type="string", length=128, nullable=true)
     */
    private $email;
    
    /**
     * @ORM\Column(name="phone", type="string", length=128, nullable=true)
     */
    private $phone;
    
    /**
     * @ORM\Column(name="certificate_id", type="string", length=128, nullable=true)
     */
    private $certificateId;
    
    /**
     * @ORM\Column(name="voucher_sent", type="boolean", nullable=true)
     */
    private $voucherSent;
    
    /**
     * @ORM\Column(name="voucher_sent_at", type="datetime", nullable=true)
     */
    private $voucherSentAt;
    
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
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    private $sfId;
    
    /**
     * @ORM\ManyToOne(targetEntity="SuitcaseItem", cascade={"all"}, inversedBy="bookings")
     * @ORM\JoinColumn(name="suitcase_item_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $suitcaseItem;

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
     * Set firstName
     *
     * @param string $firstName
     * @return Booking
     */
    public function setFirstName($firstName)
    {
        $this->firstName = $firstName;
    
        return $this;
    }

    /**
     * Get firstName
     *
     * @return string 
     */
    public function getFirstName()
    {
        return $this->firstName;
    }

    /**
     * Set lastName
     *
     * @param string $lastName
     * @return Booking
     */
    public function setLastName($lastName)
    {
        $this->lastName = $lastName;
    
        return $this;
    }

    /**
     * Get lastName
     *
     * @return string 
     */
    public function getLastName()
    {
        return $this->lastName;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return Booking
     */
    public function setEmail($email)
    {
        $this->email = $email;
    
        return $this;
    }

    /**
     * Get email
     *
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set phone
     *
     * @param string $phone
     * @return Booking
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
    
        return $this;
    }

    /**
     * Get phone
     *
     * @return string 
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * Set created
     *
     * @param \DateTime $created
     * @return Booking
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
     * @return Booking
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
     * Set suitcaseItem
     *
     * @param \Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItem
     * @return Booking
     */
    public function setSuitcaseItem(\Inertia\WinspireBundle\Entity\SuitcaseItem $suitcaseItem = null)
    {
        $this->suitcaseItem = $suitcaseItem;
    
        return $this;
    }

    /**
     * Get suitcaseItem
     *
     * @return \Inertia\WinspireBundle\Entity\SuitcaseItem 
     */
    public function getSuitcaseItem()
    {
        return $this->suitcaseItem;
    }

    /**
     * Set dirty
     *
     * @param boolean $dirty
     * @return Booking
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
     * Set sfUpdated
     *
     * @param \DateTime $sfUpdated
     * @return Booking
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
     * Set sfId
     *
     * @param string $sfId
     * @return Booking
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
     * Set certificateId
     *
     * @param string $certificateId
     * @return Booking
     */
    public function setCertificateId($certificateId)
    {
        $this->certificateId = $certificateId;
    
        return $this;
    }

    /**
     * Get certificateId
     *
     * @return string 
     */
    public function getCertificateId()
    {
        return $this->certificateId;
    }

    /**
     * Set voucherSent
     *
     * @param boolean $voucherSent
     * @return Booking
     */
    public function setVoucherSent($voucherSent)
    {
        $this->voucherSent = $voucherSent;
    
        return $this;
    }

    /**
     * Get voucherSent
     *
     * @return boolean 
     */
    public function getVoucherSent()
    {
        return $this->voucherSent;
    }

    /**
     * Set voucherSentAt
     *
     * @param \DateTime $voucherSentAt
     * @return Booking
     */
    public function setVoucherSentAt($voucherSentAt)
    {
        $this->voucherSentAt = $voucherSentAt;
    
        return $this;
    }

    /**
     * Get voucherSentAt
     *
     * @return \DateTime 
     */
    public function getVoucherSentAt()
    {
        return $this->voucherSentAt;
    }
}