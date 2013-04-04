<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="testimonial")
 * @ORM\Entity
 */
class Testimonial
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="organization", type="string", length=256, nullable=true)
     */
    private $organization;
    
    /**
     * @ORM\Column(name="name", type="string", length=256, nullable=true)
     */
    private $name;
    
    /**
     * @ORM\Column(name="title", type="string", length=256, nullable=true)
     */
    private $title;
    
    /**
     * @ORM\Column(name="quote", type="text", nullable=true)
     */
    private $quote;
    
    /**
     * @ORM\Column(name="logo", type="string", length=256, nullable=true)
     */
    private $logo;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    
    private $sfId;

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
     * Set organization
     *
     * @param string $organization
     * @return Testimonial
     */
    public function setOrganization($organization)
    {
        $this->organization = $organization;
    
        return $this;
    }

    /**
     * Get organization
     *
     * @return string 
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Testimonial
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
     * Set title
     *
     * @param string $title
     * @return Testimonial
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
     * Set quote
     *
     * @param string $quote
     * @return Testimonial
     */
    public function setQuote($quote)
    {
        $this->quote = $quote;
    
        return $this;
    }

    /**
     * Get quote
     *
     * @return string 
     */
    public function getQuote()
    {
        return $this->quote;
    }

    /**
     * Set logo
     *
     * @param string $logo
     * @return Testimonial
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;
    
        return $this;
    }

    /**
     * Get logo
     *
     * @return string 
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * Set sfId
     *
     * @param string $sfId
     * @return Testimonial
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
}