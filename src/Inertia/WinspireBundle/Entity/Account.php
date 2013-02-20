<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="account")
 * @ORM\Entity
 */
class Account
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="name", type="string", length=256, nullable=true)
     */
    private $name;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    
    private $sfId;
    
    /**
     * @ORM\Column(name="address", type="string", length=128, nullable=true)
     */
    
    private $address;
    
    /**
     * @ORM\Column(name="address2", type="string", length=128, nullable=true)
     */
    
    private $address2;
    
    /**
     * @ORM\Column(name="city", type="string", length=128, nullable=true)
     */
    
    private $city;
    
    /**
     * @ORM\Column(name="state", type="string", length=2, nullable=true)
     */
    
    private $state;
    
    /**
     * @ORM\Column(name="zip", type="string", length=64, nullable=true)
     */
    
    private $zip;
    
    /**
     * @ORM\Column(name="phone", type="string", length=64, nullable=true)
     */
    
    private $phone;
    
    /**
     * @ORM\Column(name="referred", type="string", length=256, nullable=true)
     */
    private $referred;
    
    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="accounts")
     * @ORM\JoinColumn(name="salesperson_id", referencedColumnName="id")
     */
    protected $salesperson;
    
    /**
     * @ORM\OneToMany(targetEntity="User", mappedBy="company")
     */
    protected $users;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
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
     * @return Account
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
     * Set sfId
     *
     * @param string $sfId
     * @return Account
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
     * Set address
     *
     * @param string $address
     * @return Account
     */
    public function setAddress($address)
    {
        $this->address = $address;
    
        return $this;
    }

    /**
     * Get address
     *
     * @return string 
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set city
     *
     * @param string $city
     * @return Account
     */
    public function setCity($city)
    {
        $this->city = $city;
    
        return $this;
    }

    /**
     * Get city
     *
     * @return string 
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set state
     *
     * @param string $state
     * @return Account
     */
    public function setState($state)
    {
        $this->state = $state;
    
        return $this;
    }

    /**
     * Get state
     *
     * @return string 
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Set zip
     *
     * @param string $zip
     * @return Account
     */
    public function setZip($zip)
    {
        $this->zip = $zip;
    
        return $this;
    }

    /**
     * Get zip
     *
     * @return string 
     */
    public function getZip()
    {
        return $this->zip;
    }

    /**
     * Set salesperson
     *
     * @param \Inertia\WinspireBundle\Entity\User $salesperson
     * @return Account
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
     * Add users
     *
     * @param \Inertia\WinspireBundle\Entity\User $users
     * @return Account
     */
    public function addUser(\Inertia\WinspireBundle\Entity\User $users)
    {
        $users->setAccount($this);
        $this->users[] = $users;
    
        return $this;
    }

    /**
     * Remove users
     *
     * @param \Inertia\WinspireBundle\Entity\User $users
     */
    public function removeUser(\Inertia\WinspireBundle\Entity\User $users)
    {
        $this->users->removeElement($users);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Set address2
     *
     * @param string $address2
     * @return Account
     */
    public function setAddress2($address2)
    {
        $this->address2 = $address2;
    
        return $this;
    }

    /**
     * Get address2
     *
     * @return string 
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * Set phone
     *
     * @param string $phone
     * @return Account
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
     * Set referred
     *
     * @param string $referred
     * @return Account
     */
    public function setReferred($referred)
    {
        $this->referred = $referred;
    
        return $this;
    }

    /**
     * Get referred
     *
     * @return string 
     */
    public function getReferred()
    {
        return $this->referred;
    }
}