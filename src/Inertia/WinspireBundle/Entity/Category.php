<?php
namespace Inertia\WinspireBundle\Entity;

use Gedmo\Mapping\Annotation as Gedmo;
use Doctrine\ORM\Mapping as ORM;

/**
 * @Gedmo\Tree(type="nested")
 * @ORM\Table(name="category")
 * use repository for handy tree functions
 * @ORM\Entity(repositoryClass="Gedmo\Tree\Entity\Repository\NestedTreeRepository")
 */
class Category
{
    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue
     */
    private $id;
    
    /**
     * @ORM\Column(name="title", type="string", length=64)
     */
    private $title;
    
    /**
     * @ORM\Column(name="number", type="string", length=128)
     */
    private $number;
    
    /**
     * @ORM\Column(name="rank", type="integer")
     */
    private $rank;
    
    /**
     * @ORM\Column(name="sf_id", type="string", length=128)
     */
    private $sfId;
    
    /**
     * @ORM\Column(name="slug", type="string", length=128)
     */
    private $slug;
    
    /**
     * @ORM\Column(name="open", type="boolean")
     */
    private $open;
    
    /**
     * @Gedmo\TreeLeft
     * @ORM\Column(name="lft", type="integer")
     */
    private $lft;
    
    /**
     * @Gedmo\TreeLevel
     * @ORM\Column(name="lvl", type="integer")
     */
    private $lvl;
    
    /**
     * @Gedmo\TreeRight
     * @ORM\Column(name="rgt", type="integer")
     */
    private $rgt;
    
    /**
     * @Gedmo\TreeRoot
     * @ORM\Column(name="root", type="integer", nullable=true)
     */
    private $root;
    
    /**
     * @Gedmo\TreeParent
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id", onDelete="SET NULL")
     */
    private $parent;
    
    /**
     * @ORM\OneToMany(targetEntity="Category", mappedBy="parent")
     * @ORM\OrderBy({"lft" = "ASC"})
     */
    private $children;
    
    /**
     * @ORM\ManyToMany(targetEntity="Package", mappedBy="categories")
     */
    private $packages;
    
    public function getId()
    {
        return $this->id;
    }
    
    public function setTitle($title)
    {
        $this->title = $title;
    }
    
    public function getTitle()
    {
        return $this->title;
    }
    
    public function setParent(Category $parent = null)
    {
        $this->parent = $parent;
    }
    
    public function getParent()
    {
        return $this->parent;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Set lft
     *
     * @param integer $lft
     * @return Category
     */
    public function setLft($lft)
    {
        $this->lft = $lft;
    
        return $this;
    }

    /**
     * Get lft
     *
     * @return integer 
     */
    public function getLft()
    {
        return $this->lft;
    }

    /**
     * Set lvl
     *
     * @param integer $lvl
     * @return Category
     */
    public function setLvl($lvl)
    {
        $this->lvl = $lvl;
    
        return $this;
    }

    /**
     * Get lvl
     *
     * @return integer 
     */
    public function getLvl()
    {
        return $this->lvl;
    }

    /**
     * Set rgt
     *
     * @param integer $rgt
     * @return Category
     */
    public function setRgt($rgt)
    {
        $this->rgt = $rgt;
    
        return $this;
    }

    /**
     * Get rgt
     *
     * @return integer 
     */
    public function getRgt()
    {
        return $this->rgt;
    }

    /**
     * Set root
     *
     * @param integer $root
     * @return Category
     */
    public function setRoot($root)
    {
        $this->root = $root;
    
        return $this;
    }

    /**
     * Get root
     *
     * @return integer 
     */
    public function getRoot()
    {
        return $this->root;
    }

    /**
     * Add children
     *
     * @param Inertia\WinspireBundle\Entity\Category $children
     * @return Category
     */
    public function addChildren(\Inertia\WinspireBundle\Entity\Category $children)
    {
        $this->children[] = $children;
    
        return $this;
    }

    /**
     * Remove children
     *
     * @param Inertia\WinspireBundle\Entity\Category $children
     */
    public function removeChildren(\Inertia\WinspireBundle\Entity\Category $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children
     *
     * @return Doctrine\Common\Collections\Collection 
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Set sfId
     *
     * @param string $sfId
     * @return Category
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
     * Set number
     *
     * @param string $number
     * @return Category
     */
    public function setNumber($number)
    {
        $this->number = $number;
    
        return $this;
    }

    /**
     * Get number
     *
     * @return string 
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Set rank
     *
     * @param integer $rank
     * @return Category
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
    
        return $this;
    }

    /**
     * Get rank
     *
     * @return integer 
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * Add packages
     *
     * @param \Inertia\WinspireBundle\Entity\Package $packages
     * @return Category
     */
    public function addPackage(\Inertia\WinspireBundle\Entity\Package $packages)
    {
        $this->packages[] = $packages;
    
        return $this;
    }

    /**
     * Remove packages
     *
     * @param \Inertia\WinspireBundle\Entity\Package $packages
     */
    public function removePackage(\Inertia\WinspireBundle\Entity\Package $packages)
    {
        $this->packages->removeElement($packages);
    }

    /**
     * Get packages
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getPackages()
    {
        return $this->packages;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Category
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    
        return $this;
    }

    /**
     * Get slug
     *
     * @return string 
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set open
     *
     * @param boolean $open
     * @return Category
     */
    public function setOpen($open)
    {
        $this->open = $open;
    
        return $this;
    }

    /**
     * Get open
     *
     * @return boolean 
     */
    public function getOpen()
    {
        return $this->open;
    }
}