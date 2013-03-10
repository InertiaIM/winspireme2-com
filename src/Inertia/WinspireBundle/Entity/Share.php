<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Inertia\WinspireBundle\Entity\Share
 *
 * @ORM\Table(name="share")
 * @ORM\Entity
 */
class Share
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     * @ORM\Column(name="name", type="string", length=128, nullable=true)
     */
    private $name;
    
    /**
     * @ORM\Column(name="email", type="string", length=128)
     */
    private $email;
    
    /**
     * @ORM\Column(name="message", type="text", nullable=true)
     */
    private $message;
    
    /**
     * @ORM\Column(name="token", type="string", unique=true, length=64)
     */
    private $token;
    
    /**
     * @ORM\Column(name="active", type="boolean")
     */
    private $active;
    
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
     * @ORM\ManyToOne(targetEntity="Suitcase", inversedBy="shares")
     * @ORM\JoinColumn(name="suitcase_id", referencedColumnName="id")
     */
    protected $suitcase;

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
     * @return Share
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
     * Set email
     *
     * @param string $email
     * @return Share
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
     * Set token
     *
     * @param string $token
     * @return Share
     */
    public function setToken($token)
    {
        $this->token = $token;
    
        return $this;
    }

    /**
     * Get token
     *
     * @return string 
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set active
     *
     * @param boolean $active
     * @return Share
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
     * @return Share
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
     * @return Share
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
     * @return Share
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
     * Set message
     *
     * @param string $message
     * @return Share
     */
    public function setMessage($message)
    {
        $this->message = $message;
    
        return $this;
    }

    /**
     * Get message
     *
     * @return string 
     */
    public function getMessage()
    {
        return $this->message;
    }
}