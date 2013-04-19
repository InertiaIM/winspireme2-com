<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;

class ContactSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('Here comes a Contact update...');
        
        if(!isset($notifications->Notification)) {
            $this->logger->info('notification object is bogus');
            exit;
        }
        
        $ids = array();
        
        if(!is_array($notifications->Notification)) {
            $ids[] = $notifications->Notification->sObject->Id;
        }
        else {
            foreach($notifications->Notification as $n) {
                $ids[] = $n->sObject->Id;
            }
        }
        
        foreach($ids as $id) {
            $this->logger->info('Contact Id: ' . $id);
            
            $contactResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'FirstName, ' .
                'LastName, ' .
                'Phone, ' .
                'Email, ' .
                'AccountId, ' .
                'SystemModstamp ' .
                'FROM Contact ' .
                'WHERE ' .
                'Id =\'' . $id . '\''
            );
            
            // If we don't receive a Contact, then it doesn't meet the criteria
            if(count($contactResult) == 0) {
                $this->logger->info('Contact (' . $id . ') doesn\'t meet the criteria');
                continue;
            }
            
            
            // Test whether this contact (user) is already in our database
            $user = $this->em->getRepository('InertiaWinspireBundle:User')->findOneBySfId($id);
            
            if(!$user) {
                // No match, so we stop here
                $this->logger->info('No existing Contact found (' . $id . ')');
                continue;
            }
            else {
                // User already exists, just update
                $this->logger->info('Existing Contact (' . $id . ') to be updated');
            }
            
            $sfContact = $contactResult->first();
            
            
            // CHANGE CONTACT / USER ACCOUNT
            if(isset($sfContact->AccountId)) {
                $query = $this->em->createQuery(
                    'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId = :sfid'
                )
                    ->setParameter('sfid', $sfContact->AccountId)
                ;
                
                try {
                    $account = $query->getSingleResult();
                    $this->logger->info('    Account: ' . $account->getName());
                    $user->setCompany($account);
                }
                catch (\Exception $e) {
                    $this->logger->err('    Account ID es no bueno: ' . $sfContact->AccountId);
                }
            }
            else {
                $this->logger->err('    Missing AccountID!?!?');
            }
            
            if (($sfContact->SystemModstamp > $user->getSfUpdated()) && !$user->getDirty()) {
                // CONTACT FIRST NAME
                if (isset($sfContact->FirstName)) {
                    $user->setFirstName($sfContact->FirstName);
                }
                
                // CONTACT LAST NAME
                if (isset($sfContact->LastName)) {
                    $user->setLastName($sfContact->LastName);
                }
                
                // CONTACT PHONE
                if (isset($sfContact->Phone)) {
                    $user->setPhone($sfContact->Phone);
                }
                
                // CONTACT EMAIL
                // TODO must update email and username (canonical, etc)
                // using FOS USER MANAGER
            }
            
            $account->setDirty(false);
            
            $timestamp = new \DateTime();
            $account->setSfUpdated($timestamp);
            $account->setUpdated($timestamp);
            
            $this->em->persist($user);
            $this->em->flush();
            
            $this->logger->info('User update saved...');
        }
        
        return array('Ack' => true);
    }
}