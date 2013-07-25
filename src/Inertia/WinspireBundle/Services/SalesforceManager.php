<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;

class SalesforceManager
{
    protected $em;
    protected $logger;
    protected $mailer;
    protected $sf;
    
    
    public function __construct(EntityManager $em, Client $sf, Logger $logger, \Swift_Mailer $mailer)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->sf = $sf;
    }
    
    
    public function moveSfOpportunity($suitcase, $newSfId)
    {
        $oldSfId = $suitcase->getSfId();
        
        // If the old Id and the new Id are the same, there's nothing to do
        if ($oldSfId == $newSfId) {
            return true;
        }
        
        $oppResult = $this->sf->query('SELECT ' .
            'Id, ' .
            'Name, ' .
            'AccountId, ' .
            'Event_Name__c, ' .
            'Event_Date__c, ' .
            'RecordTypeId, ' .
            'SystemModstamp ' .
            'FROM Opportunity ' .
            'WHERE ' .
            'Id =\'' . $newSfId . '\''
        );
        
        // If we don't receive an Opportunity, then it's not valid in SF
        if(count($oppResult) == 0) {
$this->logger->info('Invalid Opportunity Id provided (' . $newSfId . ')');
            return false;
        }
        
        $sfOp = $oppResult->first();
        
        // Still a possibility that the short sf_id is the same as our existing Id
        if ($sfOp->Id == $suitcase->getSfId()) {
            return true;
        }
        
        
        
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        // Test whether this Op is within the same Account
        $accountSfId = $sfOp->AccountId;
        if ($accountSfId != $account->getSfId()) {
            // The Op is in a different Account, see if we can locate it internally by sfId
            $query = $this->em->createQuery(
                'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sf_id = :sf_id'
            )->setParameter('sf_id', $accountSfId);
$this->logger->info('Opportunity in different account (' . $accountSfId . ')');
            try {
                $account = $query->getSingleResult();
$this->logger->info('Located new web site Account (' . $account->getId() . ')');
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                // If we can't get an Account record,
                // we have other problems with this move
$this->logger->info('Unable to find local Account (' . $accountSfId . ')');
                return false;
            }
            
            $user->setCompany($account);
// TODO update the SF Account record with stuff from the web site Account
$this->logger->info('Update the SF Account record with stuff from the web site Account');
            $this->em->persist($user);
        }
        else {
            
        }
        
        $suitcase->setSfId($newSfId);
        
        
        
        // Test whether this User is already a Contact within the Account
$this->logger->info('Look for whether this User/Contact is with the Account');
        $contactResult = $this->sf->query('SELECT ' .
            'Id, ' .
            'Email, ' .
            'AccountId, ' .
            'SystemModstamp ' .
            'FROM Contact ' .
            'WHERE ' .
            'Email =\'' . $user->getEmailCanonical() . '\' ' .
            'AND AccountId = \'' . $account->getSfId() . '\''
        );
        
        if(count($contactResult) != 0) {
            // An existing Contact was found within the Account
            // using the same email address
            $sfContact = $contactResult->first();
$this->logger->info('Found an existing Contact record in SF (' . $sfContact->id . ')');
            
            // Only need to make updates to the Contact/User if we're
            // looking at a different Contact from the existing User.
            if ($sfContact->Id != $user->getSfId()) {
                $sfContact->FirstName = $user->getFirstName();
                $sfContact->LastName = $user->getLastName();
                $sfContact->Phone = $user->getPhone();
                unset($sfContact->SystemModstamp);
                unset($sfContact->Email);
                
$this->logger->info('The Contact record is different (old: ' . $user-getSfId() . ', new: ' . $sfContact->id . ')');
                
//                $saveResult = $this->sf->update(array($sfContact), 'Contact');
//                
//                if($saveResult[0]->success) {
//                    $timestamp = new \DateTime();
//                    $user->setSfId($saveResult[0]->id);
//                    $user->setDirty(false);
//                    $user->setSfUpdated($timestamp);
//                    $user->setUpdated($timestamp);
//                    $this->em->persist($user);
//                }
            }
        }
        else {
            $sfContact = new \stdClass();
            $sfContact->FirstName = $user->getFirstName();
            $sfContact->LastName = $user->getLastName();
            $sfContact->Phone = $user->getPhone();
            $sfContact->Email = $user->getEmailCanonical();
            $sfContact->AccountId = $account->getSfId();
            $sfContact->Default_contact__c = 1;
            
$this->logger->info('The Contact needs to be created in the Account');
            
//            $saveResult = $this->sf->create(array($sfContact), 'Contact');
//            
//            if($saveResult[0]->success) {
//                $timestamp = new \DateTime();
//                $user->setSfId($saveResult[0]->id);
//                $user->setDirty(false);
//                $user->setSfUpdated($timestamp);
//                $user->setUpdated($timestamp);
//                $this->em->persist($user);
//            }
        }
        
        
// TODO update the basic Suitcase data into our new Opportunity
$this->logger->info('The Opportunity is being updated now');
            
        
        
        $this->em->persist($suitcase);
//        $this->em->flush();
        
        return $package;
    }
    
    
    protected function querySuitcase($user, $active = true, $order = null, $sid = null)
    {
        if (!$this->sc->isGranted('ROLE_USER')) {
            return "new";
        }
        
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
