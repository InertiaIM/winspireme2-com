<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Templating\EngineInterface;

class SuitcaseSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    protected $templating;
    protected $mailer;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger, \Swift_Mailer $mailer, EngineInterface $templating)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->templating = $templating;
        $this->mailer = $mailer;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('Here comes an Opportunity update...');
        
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
            $this->logger->info('Opportunity Id: ' . $id);
            
            $oppResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'Name, ' .
                'AccountId, ' .
                'Event_Name__c, ' .
                'Event_Date__c, ' .
                'RecordTypeId, ' .
                'PDS__Paid__c, ' .
                'SystemModstamp ' .
                'FROM Opportunity ' .
                'WHERE ' .
                'Id =\'' . $id . '\''
            );
            
            // If we don't receive an Opportunity, then it doesn't meet the criteria
            if(count($oppResult) == 0) {
                $this->logger->info('Opportunity (' . $id . ') doesn\'t meet the criteria');
                continue;
            }
            
            
            // Test whether this package is already in our database
            $suitcase = $this->em->getRepository('InertiaWinspireBundle:Suitcase')->findOneBySfId($id);
            
            if(!$suitcase) {
                // No match, so we stop here
                $this->logger->info('No existing Suitcase (' . $id . ')');
                continue;
            }
            else {
                // Suitcase already exists, just update
                $this->logger->info('Existing Suitcase (' . $id . ') to be updated');
            }
            
            $sfOpp = $oppResult->first();
            
            if ($sfOpp->SystemModstamp >= $suitcase->getSfUpdated()) {
                $suitcase->setName($sfOpp->Name);
                $suitcase->setEventName($sfOpp->Name);
                
                if(isset($sfOpp->Event_Date__c)) {
                    $suitcase->setEventDate($sfOpp->Event_Date__c);
                }
                
                // If the Suitcase isn't already marked as "paid" (status = A)
                $newWebStatus = false;
                if (isset($sfOpp->PDS__Paid__c) && $sfOpp->PDS__Paid__c == '1' && $suitcase->getStatus() != 'A') {
                    $timestamp = new \DateTime();
                    $suitcase->setStatus('A');
                    $newWebStatus = 'Paid';
                    $suitcase->setInvoicePaidAt($timestamp);
                    $this->sendEmail($suitcase);
                }
                
                // If the Suitcase _is_ already marked as "paid", but the Opportunity has been changed to unpaid
                if ((!isset($sfOpp->PDS__Paid__c) || $sfOpp->PDS__Paid__c == '0') && $suitcase->getStatus() == 'A') {
                    $suitcase->setStatus('I');
                    $newWebStatus = 'Invoiced';
                    $suitcase->setInvoicePaidAt(null);
                }
                
                // CHANGE SUITCASE USER ACCOUNT
                if(isset($sfOpp->AccountId)) {
                    $user = $suitcase->getUser();
                    
                    $query = $this->em->createQuery(
                        'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId = :sfid'
                    )
                        ->setParameter('sfid', $sfOpp->AccountId)
                    ;
                    
                    try {
                        $account = $query->getSingleResult();
                        $this->logger->info('    Account: ' . $account->getName());
                        
                        $previousOwner = $user->getCompany()->getSalesperson();
                        $user->setCompany($account);
                        
                        if ($account->getSalesperson()->getUsername() != 'confirmation@winspireme.com' &&
                            $previousOwner->getId() != $account->getSalesperson()->getId()) {
                            // The user was moved to a new Account with a different EC
                            // Send the user an email introduction to their new EC
                            $name = $user->getFirstName() . ' ' .
                                $user->getLastName();
                            
                            $email = $user->getEmail();
                            
                            $locale = strtolower($user->getCompany()->getCountry());
                            
                            $salesperson = array(
                                $user->getCompany()->getSalesperson()->getEmail() =>
                                $user->getCompany()->getSalesperson()->getFirstName() . ' ' .
                                $user->getCompany()->getSalesperson()->getLastName()
                            );
                            
                            $message = \Swift_Message::newInstance()
                                ->setSubject('Introducing your Winspire Event Consultant')
                                ->setReplyTo($salesperson)
                                ->setSender(array('info@winspireme.com' => 'Winspire'))
                                ->setFrom($salesperson)
                                ->setTo(array($email => $name))
                                ->setBcc(array($user->getCompany()->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
                                ->setBody(
                                    $this->templating->render(
                                        'InertiaWinspireBundle:Email:event-consultant-intro.html.twig',
                                        array(
                                            'user' => $user,
                                            'from' => $user->getCompany()->getSalesperson()->getEmail(),
                                            'locale' => $locale,
                                        )
                                    ),
                                    'text/html'
                                )
                                ->addPart(
                                    $this->templating->render(
                                        'InertiaWinspireBundle:Email:event-consultant-intro.txt.twig',
                                        array(
                                            'user' => $user,
                                            'from' => $user->getCompany()->getSalesperson()->getEmail(),
                                            'locale' => $locale,
                                        )
                                    ),
                                    'text/plain'
                                )
                            ;
                            $this->mailer->send($message);
                        }
                    }
                    catch (\Exception $e) {
                        $this->logger->err('    Account ID es no bueno: ' . $sfOpp->AccountId);
                    }
                    
                    $this->em->persist($user);
                }
                else {
                    $this->logger->err('    Missing AccountID!?!?');
                }
                
                
                $timestamp = new \DateTime();
                $suitcase->setDirty(false);
                $suitcase->setSfUpdated($sfOpp->SystemModstamp);
                $suitcase->setUpdated($timestamp);
                $this->em->persist($suitcase);
                
                
                if ($newWebStatus) {
                    unset($sfOpp->SystemModstamp);
                    $sfOpp->Website_suitcase_status__c = $newWebStatus;
                    $saveResult = $this->sf->update(array($sfOpp), 'Opportunity');
                    
                    if (!$saveResult[0]->success) {
                        $this->logger->info('Problems updating the Opportunity to \'Paid\' or \'Invoiced\'.');
                    }
                }
            }
            
            $this->em->flush();
            
            $this->logger->info('Suitcase / User update saved...');
        }
        
        return array('Ack' => true);
    }
    
    
    protected function sendEmail($suitcase) {
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        $name = $user->getFirstName() . ' ' .
            $user->getLastName();
        
        $email = $user->getEmail();
        
        $locale = strtolower($account->getCountry());
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Your Booking Vouchers are ready to deliver!')
            ->setSender(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBcc(array($account->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
        ;

        if ($suitcase->getUser()->getCompany()->getSalesperson()->getId() != 1) {
            $sperson = $suitcase->getUser()->getCompany()->getSalesperson();
            $message->setReplyTo(array($sperson->getEmail() => $sperson->getFirstName() . ' ' . $sperson->getLastName()));
            $message->setFrom(array($sperson->getEmail() => $sperson->getFirstName() . ' ' . $sperson->getLastName()));
            $from = $sperson->getEmail();
        }
        else {
            $message->setFrom(array('info@winspireme.com' => 'Winspire'));
            $from = 'info@winspireme.com';
        }

        $message
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:travel-vouchers-ready.html.twig',
                    array(
                        'suitcase' => $suitcase,
                        'from' => $from,
                        'locale' => $locale,
                    )
                ), 'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:travel-vouchers-ready.txt.twig',
                    array(
                        'suitcase' => $suitcase,
                        'from' => $from,
                        'locale' => $locale,
                    )
                ), 'text/plain'
            )
        ;

        
        $this->mailer->send($message);
    }
}
