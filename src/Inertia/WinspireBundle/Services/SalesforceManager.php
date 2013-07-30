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
    protected $winnieId = '005700000013DkmAAE';
    
    
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
            return $oldSfId;
        }
        
        $oppResult = $this->sf->query('SELECT ' .
            'Id, ' .
            'Name, ' .
            'AccountId, ' .
            'Event_Name__c, ' .
            'Event_Date__c, ' .
            'RecordTypeId, ' .
            'SystemModstamp, ' .
            'CreatedById ' .
            'FROM Opportunity ' .
            'WHERE ' .
            'Id =\'' . $newSfId . '\''
        );
        
        // If we don't receive an Opp, then it's not valid in SF
        if(count($oppResult) == 0) {
$this->logger->info('Invalid Opportunity Id provided (' . $newSfId . ')');
            return false;
        }
        
        $sfOpp = $oppResult->first();
        
        // Still a possibility that the short sf_id is the same as our existing Id
        if ($sfOpp->Id == $suitcase->getSfId()) {
$this->logger->info('Old Opp = New Opp');
            return $suitcase->getSfId();
        }
        
        $user = $suitcase->getUser();
        $oldAccount = $user->getCompany();
        
        
        // ACCOUNT
        // Test whether this Opp is within the same Account
        $accountSfId = $sfOpp->AccountId;
        if ($accountSfId != $oldAccount->getSfId()) {
            // The Opp is in a different Account, see if we can locate it internally by sfId
$this->logger->info('Opp is from a different account (' . $accountSfId . ')');
            $query = $this->em->createQuery(
                'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId = :sf_id'
            )->setParameter('sf_id', $accountSfId);
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
$this->logger->info('Update the Account record with data from the "old" Account');
            // Update the new Account with data from our old Account
            $account->setName($oldAccount->getName());
            $account->setAddress($oldAccount->getAddress());
            $account->setAddress2($oldAccount->getAddress2());
            $account->setCity($oldAccount->getCity());
            $account->setState($oldAccount->getState());
            $account->setZip($oldAccount->getZip());
            $account->setCountry($oldAccount->getCountry());
            $account->setPhone($oldAccount->getPhone());
            $account->setReferred($oldAccount->getReferred());
            $account->setNameCanonical($oldAccount->getNameCanonical());
            
// TODO update the SF Account record with data from the web site Account
$this->logger->info('Update the SF Account record with stuff from the web site Account');
            $sfAccount = new \stdClass();
            $sfAccount->Id = $accountSfId;
            $sfAccount->Name = $account->getName();
            $address = $account->getAddress();
            if ($account->getAddress2() != '') {
                $address .= chr(10) . $account->getAddress2();
            }
            $sfAccount->BillingStreet = $address;
            $sfAccount->BillingCity = $account->getCity();
            $sfAccount->BillingState = $account->getState();
            $sfAccount->BillingPostalCode = $account->getZip();
            $sfAccount->BillingCountry = ($account->getCountry() == 'CA' ? 'Canada' : 'United States');
            $sfAccount->Phone = $account->getPhone();
            $sfAccount->Referred_by__c = $account->getReferred();
            
            $saveResult = $this->sf->update(array($sfAccount), 'Account');
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $account->setSfId($saveResult[0]->id);
                $account->setDirty(false);
                $account->setSfUpdated($timestamp);
                $account->setUpdated($timestamp);
                $this->em->persist($account);
            }
            else {
                return false;
            }
            
            $this->em->persist($user);
            
            
            
            //TODO test for whether the original Account was deleted from SF,
            // so we can delete it from our local database
            $accountResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'SystemModstamp, ' .
                'CreatedById ' .
                'FROM Account ' .
                'WHERE ' .
                'Id =\'' . $oldAccount->getSfId() . '\''
            );
            if(count($accountResult) == 0) {
$this->logger->info('Old Account is missing from SF (' . $oldAccount->getSfId() . ')');
                $this->em->remove($oldAccount);
            }
            else {
$this->logger->info('Now we have two Accounts with the same name?');
                $this->sendForHelp(
                    'Two of the same Account now exists' . "\n" .
                    'old Account: ' . $oldAccount->getSfId() . "\n" .
                    'new Account: ' . $account->getSfId()
                );
            }
        }
        else {
$this->logger->info('Opportunity in same account (' . $accountSfId . ')');
        }
        
        $suitcase->setSfId($newSfId);
        
        
        
        // Test whether this User is already a Contact within the Account
$this->logger->info('Look for whether this User/Contact is within the Account');
        $contactResult = $this->sf->query('SELECT ' .
            'Id, ' .
            'Email, ' .
            'AccountId, ' .
            'SystemModstamp, ' .
            'CreatedById ' .
            'FROM Contact ' .
            'WHERE ' .
            'Email =\'' . $user->getEmailCanonical() . '\' ' .
            'AND AccountId = \'' . $account->getSfId() . '\''
        );
        
        if(count($contactResult) != 0) {
            // An existing Contact was found within the Account
            // using the same email address
            $sfContact = $contactResult->first();
$this->logger->info('Found an existing Contact record in SF (' . $sfContact->Id . ')');
            
            // Only need to make updates to the Contact/User if we're
            // looking at a different Contact from the existing User.
            if ($sfContact->Id != $user->getSfId()) {
                $sfContact->FirstName = $user->getFirstName();
                $sfContact->LastName = $user->getLastName();
                $sfContact->Phone = $user->getPhone();
                unset($sfContact->SystemModstamp);
                unset($sfContact->CreatedById);
                unset($sfContact->Email);
                
$this->logger->info('The Contact record is different (old: ' . $user->getSfId() . ', new: ' . $sfContact->Id . ')');
                
                $saveResult = $this->sf->update(array($sfContact), 'Contact');
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $user->setSfId($saveResult[0]->id);
                    $user->setDirty(false);
                    $user->setSfUpdated($timestamp);
                    $user->setUpdated($timestamp);
                    $this->em->persist($user);
                }
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
            
            $saveResult = $this->sf->create(array($sfContact), 'Contact');
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $user->setSfId($saveResult[0]->id);
                $user->setDirty(false);
                $user->setSfUpdated($timestamp);
                $user->setUpdated($timestamp);
                $this->em->persist($user);
            }
        }
        
        
$this->logger->info('The Opportunity is being updated now');
        $sfOpp->Name = substr($suitcase->getEventName(), 0, 40);
        $sfOpp->Website_suitcase_status__c = 'Unpacked';
        
        $sfOpp->Event_Name__c = substr($suitcase->getEventName(), 0, 40);
        if ($suitcase->getEventDate() != '') {
            $sfOpp->Event_Date__c = $suitcase->getEventDate();
            $sfOpp->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
        }
        else {
            $sfOpp->Event_Date__c = new \DateTime('+30 days');
            $sfOpp->CloseDate = new \DateTime('+60 days');
        }
        
        $oldCreatedById = $sfOpp->CreatedById;
        
        unset($sfOpp->SystemModstamp);
        unset($sfOpp->CreatedById);
        $saveResult = $this->sf->update(array($sfOpp), 'Opportunity');
        
        if($saveResult[0]->success) {
            $timestamp = new \DateTime();
            $suitcase->setSfId($saveResult[0]->id);
            $suitcase->setDirty(false);
            $suitcase->setSfUpdated($timestamp);
            $suitcase->setUpdated($timestamp);
        }
        else {
            return false;
        }
        
        
        
        if ($oldCreatedById == $this->winnieId) {
$this->logger->info('The Old Opp was created by Winnie; so we\'ll delete it');
            // If the original Opp was created by Winnie
            // we can delete it from SF
            $deleteResult = $this->sf->delete(array($oldSfId));
            
            if(!$deleteResult[0]->success) {
                return false;
            }
$this->logger->info('The Old Opp was deleted (' . $oldSfId . ')');
        }
        
        
        $this->em->persist($suitcase);
        
        
        try {
            $this->em->flush();
        }
        catch (\Exception $e) {
            $this->sendForHelp(
                $e->getMessage()
            );
        }
        
        
        
        
        
        
        
        return $suitcase->getSfId();
    }
    
    protected function sendForHelp($message)
    {
        $message = \Swift_Message::newInstance()
        ->setSubject('Winspire::Problem during Suitcase SF Reassignment')
        ->setFrom(array('notice@winspireme.com' => 'Winspire'))
        ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
        ->setBody($message,
            'text/plain'
        )
        ;
        
        $this->mailer->send($message);
    }
}
