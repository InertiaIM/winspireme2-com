<?php
namespace Inertia\WinspireBundle\Services;

use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Booking;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\SecurityContext;

class SuitcaseManager
{
    protected $em;
    protected $producer;
    protected $sc;
    protected $session;
    protected $suitcaseUser;
    
    public function __construct(EntityManager $em, Session $session, SecurityContext $sc, Producer $producer)
    {
        $this->em = $em;
        $this->producer = $producer;
        $this->sc = $sc;
        $this->session = $session;
        
        $this->suitcaseUser = $this->sc->getToken()->getUser();
        
        // Admin users should have the $user replaced by their customer.
        // So we'll first query for the latest Suitcase the salesperson
        // was working with, and make that the user.
        // TODO for better performance, we should store the user in the admin session?
        if ($this->sc->isGranted('ROLE_ADMIN')) {
            $uid = $this->session->get('uid');
            
            $qb = $this->em->createQueryBuilder();
            $qb->select(array('u'));
            $qb->from('InertiaWinspireBundle:User', 'u');
            $qb->andWhere('u.id = :id');
            $qb->setParameter('id', $uid);
            try {
                $user = $qb->getQuery()->getSingleResult();
                $this->suitcaseUser = $user;
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                // Can't find a user to work with; so we're done...
                $this->suitcaseUser = false;
            }
        }
    }
    
    
    public function addToSuitcase($suitcase, $packageId)
    {
        // First test whether this package is already in the Suitcase
        $query = $this->em->createQuery(
            'SELECT i FROM InertiaWinspireBundle:SuitcaseItem i WHERE i.suitcase = :suitcase_id AND i.package = :package_id AND i.status != \'X\''
        )
        ->setParameter('suitcase_id', $suitcase->getId())
        ->setParameter('package_id', $packageId)
        ;
        
        $items = $query->getResult();
        if (count($items) > 0) {
            return false;
        }
        
        
        // Second step is to check whether this package is "private" (only available to admins)
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('p'));
        $qb->from('InertiaWinspireBundle:Package', 'p');
        $qb->where('p.id = :package_id');
        $qb->setParameter('package_id', $packageId);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('p.is_private != 1');
        }
        
        $packages = $qb->getQuery()->getResult();
        if (count($packages) < 1) {
            return false;
        }
        else {
            $package = $packages[0];
        }
        
        $suitcaseItem = new SuitcaseItem();
        $suitcaseItem->setPackage($package);
        $suitcaseItem->setQuantity(0);
        $suitcaseItem->setPrice(0);
        $suitcaseItem->setCost(0);
        $suitcaseItem->setSubtotal(0);
        
        if ($this->sc->isGranted('ROLE_ADMIN')) {
            $suitcaseItem->setStatus('R');
        }
        else {
            $suitcaseItem->setStatus('M');
        }
        $this->em->persist($suitcaseItem);
        
        $suitcase->addItem($suitcaseItem);
        if($suitcase->getStatus() != 'U') {
            // reopen suitcase and trigger reminder message
            $suitcase->setStatus('U');
            $suitcase->setDirty(true);
            
            $msg = array('suitcase_id' => $suitcase->getId());
            $this->producer->publish(serialize($msg), 'unpack-suitcase');
        }
        $suitcase->setUpdated($suitcaseItem->getUpdated());
        
        $this->em->persist($suitcase);
        $this->em->flush();
        
        return $package;
    }
    
    
    public function deleteFromSuitcase($suitcase, $packageId)
    {
        $items = $suitcase->getItems();
        $deleted = false;
        foreach($items as $item) {
            if($packageId == $item->getPackage()->getId()) {
                $item->setStatus('X');
                $suitcase->setUpdated(new \DateTime());
                
                if($suitcase->getPacked()) {
                    // reopen suitcase and trigger reminder message
                    $suitcase->setStatus('U');
                    $suitcase->setDirty(true);
                    
                    $msg = array('suitcase_id' => $suitcase->getId());
                    $this->producer->publish(serialize($msg), 'unpack-suitcase');
                }
                
                $this->em->persist($item);
                $this->em->persist($suitcase);
                $this->em->flush();
                
                $deleted = true;
            }
        }
        
        return $deleted;
    }
    
    
    public function flagSuitcaseItem($suitcase, $packageId)
    {
        $items = $suitcase->getItems();
        $newStatus = false;
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($items as $item) {
            $counts[$item->getStatus()]++;
            
            if($packageId == $item->getPackage()->getId()) {
                switch($item->getStatus()) {
                    case 'M':
                        $item->setStatus('D');
                        $newStatus = 'D';
                        $counts['M']--;
                        $counts['D']++;
                        break;
                    case 'D':
                        $item->setStatus('M');
                        $newStatus = 'M';
                        $counts['D']--;
                        $counts['M']++;
                        break;
                    case 'R':
                        $item->setStatus('E');
                        $newStatus = 'E';
                        $counts['R']--;
                        $counts['E']++;
                        break;
                    case 'E':
                        $item->setStatus('R');
                        $newStatus = 'R';
                        $counts['E']--;
                        $counts['R']++;
                        break;
                }
                
                $this->em->persist($item);
                $this->em->flush();
            }
        }
        
        return array('status' => $newStatus, 'counts' => $counts);
    }
    
    
    public function flagSuitcaseItems($suitcase, $ids)
    {
        $items = $suitcase->getItems();
        
        foreach($ids as $element) {
            foreach($items as $item) {
                if($element['id'] == $item->getPackage()->getId()) {
                    $item->setStatus($element['status']);
                    $this->em->persist($item);
                }
            }
        }
        $this->em->flush();
        
        return $this->getCounts($suitcase);
    }
    
    
    public function getCounts($suitcase)
    {
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            if ($item->getStatus() != 'X') {
                $counts[$item->getStatus()]++;
            }
        }
        
        return $counts;
    }
    
    
    public function getSuitcase($active = true, $order = null)
    {
        $sid = $this->session->get('sid');
         
        if (!$this->suitcaseUser) {
            return false;
        }
        
        $suitcase = $this->querySuitcase($this->suitcaseUser, $active, $order, $sid);
        if ($suitcase != 'new') {
            $this->session->set('sid', $suitcase->getId());
            $this->session->save();
            return $suitcase;
        }
        else {
            $this->session->set('sid', 'new');
            $this->session->save();
            return 'new';
        }
    }
    
    public function getSuitcaseList($active = true)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        
        // If "active", we only want packed or unpacked Suitcases
        if ($active) {
            $qb->where($qb->expr()->in('s.status', array('U', 'P')));
        }
        
        $qb->andWhere('s.user = :user_id');
        $qb->setParameter('user_id', $this->suitcaseUser->getId());
        
        $qb->orderBy('s.name', 'ASC');
        
        $suitcases = $qb->getQuery()->getResult();
        
        $suitcaseList = array();
        foreach($suitcases as $s) {
            $suitcaseList[] = array('id' => $s->getId(), 'name' => $s->getName());
        }
        
        return $suitcaseList;
    }
    
    
    public function packSuitcase($suitcase)
    {
        
    }
    
    
    public function requestInvoice($qtys)
    {
        $ids = array_keys($qtys);
        
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:SuitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->join('i.package', 'p');
        $qb->where('i.status != \'X\'');
        $qb->andWhere($qb->expr()->in('i.id', ':ids'));
        $qb->setParameter('ids', $ids);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        $items = $qb->getQuery()->getResult();
        foreach ($items as $item) {
            if (isset($qtys[$item->getId()])) {
                $item->setQuantity($qtys[$item->getId()]);
                $item->setCost($item->getPackage()->getCost());
                $item->setSubtotal($item->getQuantity() * $item->getCost());
                $this->em->persist($item);
                
                for ($i=0; $i<$item->getQuantity(); $i++) {
                    $booking = new Booking();
                    $item->addBooking($booking);
                    $this->em->persist($booking);
                }
            }
        }
        
        if (count($items) > 0) {
            $suitcase = $items[0]->getSuitcase();
            $suitcase->setStatus('R');
            $suitcase->setDirty(true);
            $suitcase->setInvoiceRequestedAt(new \DateTime());
            $this->em->persist($suitcase);
            $this->em->flush();
            
            $msg = array('suitcase_id' => $suitcase->getId());
            $this->producer->publish(serialize($msg), 'invoice-request');
            
            return $suitcase;
        }
        else {
            return false;
        }
    }
    
    
    public function updateSuitcaseQty($id, $qty)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('i', 'p'));
        $qb->from('InertiaWinspireBundle:SuitcaseItem', 'i');
        $qb->join('i.suitcase', 's');
        $qb->join('i.package', 'p');
        $qb->where('i.status != \'X\'');
        $qb->andWhere('i.id = :id');
        $qb->setParameter('id', $id);
        
        if (!$this->sc->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('s.user = :user');
            $qb->setParameter('user', $this->suitcaseUser);
        }
        
        try {
            $item = $qb->getQuery()->getSingleResult();
            $item->setCost($item->getPackage()->getCost());
            $item->setQuantity($qty);
            $item->setSubTotal($item->getCost() * $item->getQuantity());
            $this->em->persist($item);
            $this->em->flush();
            $count = 1;
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $count = 0;
        }
        
        return $count;
    }
    
    
    protected function querySuitcase($user, $active = true, $order = null, $sid = null)
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        $qb->leftJoin('s.items', 'i', 'WITH', 'i.status != \'X\'');
        $qb->leftJoin('i.package', 'p');
        
        // If "active", we only want packed or unpacked Suitcases
        if ($active) {
            $qb->where($qb->expr()->in('s.status', array('U', 'P')));
        }
        
        $qb->andWhere('s.user = :user_id');
        $qb->setParameter('user_id', $user->getId());
        
        if ($sid) {
            $qb->andWhere('s.id = :id');
            $qb->setParameter('id', $sid);
        }
        
        $qb->orderBy('s.updated', 'DESC');
        
        // Set the sort order based on our "order" parameter
        if ($order == 'update') {
            $qb->addOrderBy('i.updated', 'DESC');
        }
        else {
            $qb->addOrderBy('p.parent_header', 'ASC');
        }
        
        
        $suitcases = $qb->getQuery()->getResult();
        if (count($suitcases) == 0) {
            if ($sid) {
                $suitcase = $this->querySuitcase($user, $active, $order);
            }
            else {
                $suitcase = 'new';
            }
        }
        else {
            $suitcase = $suitcases[0];
        }
        
        
        return $suitcase;
    }
}