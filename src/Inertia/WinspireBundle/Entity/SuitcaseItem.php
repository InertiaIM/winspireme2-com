<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Table(name="suitcase_item")
 * @ORM\Entity
 */
class SuitcaseItem
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="quantity", type="integer")
     */
    private $quantity;
    
    /**
     * @ORM\Column(name="cost", type="decimal", scale=2)
     */
    private $cost;
    
    /**
     * @ORM\Column(name="subtotal", type="decimal", scale=2)
     */
    private $subtotal;
    
    /**
     * @ORM\Column(name="price", type="decimal", scale=2)
     */
    private $price;
    
    /**
     * @ORM\Column(name="status", type="string", length=128)
     */
    private $status;
    
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
     * @ORM\ManyToOne(targetEntity="Suitcase", inversedBy="items")
     * @ORM\JoinColumn(name="suitcase_id", referencedColumnName="id", onDelete="CASCADE")
     */
    protected $suitcase;
    
    /**
     * @ORM\ManyToOne(targetEntity="Package", inversedBy="suitcaseItems")
     * @ORM\JoinColumn(name="package_id", referencedColumnName="id")
     */
    protected $package;

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
     * Set quantity
     *
     * @param integer $quantity
     * @return SuitcaseItem
     */
    public function setQuantity($quantity)
    {
        $this->quantity = $quantity;
    
        return $this;
    }

    /**
     * Get quantity
     *
     * @return integer 
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Set price
     *
     * @param float $price
     * @return SuitcaseItem
     */
    public function setPrice($price)
    {
        $this->price = $price;
    
        return $this;
    }

    /**
     * Get price
     *
     * @return float 
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * Set subtotal
     *
     * @param float $subtotal
     * @return SuitcaseItem
     */
    public function setSubtotal($subtotal)
    {
        $this->subtotal = $subtotal;
    
        return $this;
    }

    /**
     * Get subtotal
     *
     * @return float 
     */
    public function getSubtotal()
    {
        return $this->subtotal;
    }

    /**
     * Set status
     *
     * @param string $status
     * @return SuitcaseItem
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
     * Set sfId
     *
     * @param string $sfId
     * @return SuitcaseItem
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
     * @return SuitcaseItem
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
     * @return SuitcaseItem
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
     * Set suitcase
     *
     * @param \Inertia\WinspireBundle\Entity\Suitcase $suitcase
     * @return SuitcaseItem
     */
    public function setSuitcase(\Inertia\WinspireBundle\Entity\Suitcase $suitcase = null)
    {
        $this->suitcase = $suitcase;
    
        return $this;
    }

    /**
     * Get suitcase
     *
     * @return \Inertia\WinspireBundle\Entity\Suitcase 
     */
    public function getSuitcase()
    {
        return $this->suitcase;
    }

    /**
     * Set package
     *
     * @param \Inertia\WinspireBundle\Entity\Package $package
     * @return SuitcaseItem
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

    /**
     * Set cost
     *
     * @param float $cost
     * @return SuitcaseItem
     */
    public function setCost($cost)
    {
        $this->cost = $cost;
    
        return $this;
    }

    /**
     * Get cost
     *
     * @return float 
     */
    public function getCost()
    {
        return $this->cost;
    }
}