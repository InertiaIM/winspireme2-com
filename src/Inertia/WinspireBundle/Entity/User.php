<?php
namespace Inertia\WinspireBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * Inertia\WinspireBundle\Entity\User
 *
 * @ORM\Table(name="user", indexes={@ORM\Index(name="sf_id", columns={"sf_id"})})
 * @ORM\Entity
 */
class User extends BaseUser implements \Serializable
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
     * @ORM\Column(name="type", type="string", length=1, nullable=true)
     */
    private $type;
    
    /**
     * @ORM\Column(name="phone", type="string", length=64, nullable=true)
     */
    private $phone;
    
    /**
     * @ORM\Column(name="newsletter", type="boolean")
     */
    private $newsletter;
    
    /**
     * @ORM\Column(name="dirty", type="boolean", nullable=true)
     */
    private $dirty;
    
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
     * @ORM\Column(name="sf_updated", type="datetime", nullable=true)
     */
    private $sfUpdated;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128, nullable=true)
     */
    private $sfId;
    
    /**
     * @ORM\OneToMany(targetEntity="Suitcase", mappedBy="user")
     */
    protected $suitcases;
    
    /**
     * @ORM\OneToMany(targetEntity="Account", mappedBy="user")
     */
    protected $accounts;
    
    /**
     * @ORM\ManyToOne(targetEntity="Account", inversedBy="users")
     * @ORM\JoinColumn(name="account_id", referencedColumnName="id")
     */
    protected $company;
    
    
    public function __construct()
    {
        parent::__construct();
        
        $this->suitcases = new \Doctrine\Common\Collections\ArrayCollection();
        // your own logic
    }
    
    
    /**
     * @see \Serializable::serialize()
     */
    public function serialize()
    {
        return serialize(array(
            $this->id,
        ));
    }
    
    /**
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        list (
            $this->id,
        ) = unserialize($serialized);
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
     * Add suitcases
     *
     * @param \Inertia\WinspireBundle\Entity\Suitcase $suitcases
     * @return User
     */
    public function addSuitcase(\Inertia\WinspireBundle\Entity\Suitcase $suitcases)
    {
        $suitcases->setUser($this);
        $this->suitcases[] = $suitcases;
        
        return $this;
    }

    /**
     * Remove suitcases
     *
     * @param \Inertia\WinspireBundle\Entity\Suitcase $suitcases
     */
    public function removeSuitcase(\Inertia\WinspireBundle\Entity\Suitcase $suitcases)
    {
        $this->suitcases->removeElement($suitcases);
    }

    /**
     * Get suitcases
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSuitcases()
    {
        return $this->suitcases;
    }

    /**
     * Add accounts
     *
     * @param \Inertia\WinspireBundle\Entity\Account $accounts
     * @return User
     */
    public function addAccount(\Inertia\WinspireBundle\Entity\Account $accounts)
    {
        $this->accounts[] = $accounts;
    
        return $this;
    }

    /**
     * Remove accounts
     *
     * @param \Inertia\WinspireBundle\Entity\Account $accounts
     */
    public function removeAccount(\Inertia\WinspireBundle\Entity\Account $accounts)
    {
        $this->accounts->removeElement($accounts);
    }

    /**
     * Get accounts
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getAccounts()
    {
        return $this->accounts;
    }

    /**
     * Set account
     *
     * @param \Inertia\WinspireBundle\Entity\Account $account
     * @return User
     */
    public function setAccount(\Inertia\WinspireBundle\Entity\Account $account = null)
    {
        $this->account = $account;
    
        return $this;
    }

    /**
     * Get account
     *
     * @return \Inertia\WinspireBundle\Entity\Account 
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Set company
     *
     * @param \Inertia\WinspireBundle\Entity\Account $company
     * @return User
     */
    public function setCompany(\Inertia\WinspireBundle\Entity\Account $company = null)
    {
        $this->company = $company;
    
        return $this;
    }

    /**
     * Get company
     *
     * @return \Inertia\WinspireBundle\Entity\Account 
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * Set firstName
     *
     * @param string $firstName
     * @return User
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
     * @return User
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
     * Set sfId
     *
     * @param string $sfId
     * @return User
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
     * Set type
     *
     * @param string $type
     * @return User
     */
    public function setType($type)
    {
        $this->type = $type;
    
        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set phone
     *
     * @param string $phone
     * @return User
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
     * Set newsletter
     *
     * @param boolean $newsletter
     * @return User
     */
    public function setNewsletter($newsletter)
    {
        $this->newsletter = $newsletter;
    
        return $this;
    }

    /**
     * Get newsletter
     *
     * @return boolean 
     */
    public function getNewsletter()
    {
        return $this->newsletter;
    }

    /**
     * Set dirty
     *
     * @param boolean $dirty
     * @return User
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
     * Set created
     *
     * @param \DateTime $created
     * @return User
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
     * @return User
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
     * Set sfUpdated
     *
     * @param \DateTime $sfUpdated
     * @return User
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
}