<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Services\SuitcaseManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class PackSuitcaseConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    protected $sm;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, Client $salesforce, SuitcaseManager $sm)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->sf = $salesforce;
        $this->sm = $sm;
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        $this->sf->logout();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
        $body = unserialize($msg->body);
        $suitcaseId = $body['suitcase_id'];
        $first = $body['first'];
        
        $query = $this->em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.id = :id ORDER BY i.updated DESC'
        )->setParameter('id', $suitcaseId);
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // If we can't get the Suitcase record we'll 
            // throw out the message from the queue (ack)
            return true;
        }
        
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        // Make sure we have our user setup as an OpportunityContactRole
        if ($user->getSfId() != '' && $suitcase->getSfId() != '' && $suitcase->getSfContactRoleId() == '') {
            $sfOpportunityContactRole = new \stdClass();
            $sfOpportunityContactRole->ContactId = $user->getSfId();
            $sfOpportunityContactRole->IsPrimary = true;
            $sfOpportunityContactRole->OpportunityId = $suitcase->getSfId();
            $sfOpportunityContactRole->Role = 'Website user';
            
            try {
                $saveResult = $this->sf->create(array($sfOpportunityContactRole), 'OpportunityContactRole');
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $suitcase->setSfContactRoleId($saveResult[0]->id);
                    $this->em->persist($suitcase);
                    $this->em->flush();
                }
            }
            catch (\Exception $e) {
                $this->sendForHelp($e, $suitcase);
                $this->sf->logout();
                
                return true;
            }
        }
        
        
        
        // Salesforce Updates
        $sfOpportunity = new \stdClass();
        $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
        $sfOpportunity->Website_suitcase_status__c = 'Packed';
        $sfOpportunity->LOA_Received__c = 1;
        $sfOpportunity->Event_Name__c = substr($suitcase->getEventName(), 0, 40);
        if ($suitcase->getEventDate() != '') {
            $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
            $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
        }
        else {
            $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
            $sfOpportunity->CloseDate = new \DateTime('+60 days');
        }
        $sfOpportunity->AccountId = $account->getSfId();
        
        try {
            if ($suitcase->getSfId() == '') {
                // We haven't done an initial sync of the Suitcase?
                $sfOpportunity->Type = 'Web Suitcase';
                $sfOpportunity->StageName = 'Counsel';
                $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
                $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
                $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
                $sfOpportunity->Item_Use__c = 'Silent Auction';
                $saveResult = $this->sf->create(array($sfOpportunity), 'Opportunity');
            }
            else {
                $sfOpportunity->Id = $suitcase->getSfId();
                $saveResult = $this->sf->update(array($sfOpportunity), 'Opportunity');
            }
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $suitcase->setSfId($saveResult[0]->id);
                $suitcase->setDirty(false);
                $suitcase->setSfUpdated($timestamp);
                $suitcase->setUpdated($timestamp);
                $this->em->persist($suitcase);
                $this->em->flush();
            }
            else {
// TODO LOG A MESSAGE.  SOMETHING BAD HAPPENED WITH SF
            }
        }
        catch (\Exception $e) {
            $this->sendForHelp($e, $suitcase);
            $this->sf->logout();
            
            return true;
        }
        
        if ($account->getSfId() != '') {
            $address = $account->getAddress();
            if ($account->getAddress2() != '') {
                $address .= chr(10) . $account->getAddress2();
            }
            
            $sfAccount = new \stdClass();
            $sfAccount->Id = $account->getSfId();
            $sfAccount->BillingStreet = $address;
            $sfAccount->BillingCity = $account->getCity();
            $sfAccount->BillingState = $account->getState();
            $sfAccount->BillingPostalCode = $account->getZip();
            $sfAccount->Phone = $account->getPhone();
            $sfAccount->AccountSource = $account->getSource();
            $sfAccount->Referred_by__c = substr($account->getReferred(), 0, 50);
            
            try {
                $saveResult = $this->sf->update(array($sfAccount), 'Account');
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $account->setDirty(false);
                    $account->setSfId($saveResult[0]->id);
                    $account->setSfUpdated($timestamp);
                    $account->setUpdated($timestamp);
                    $this->em->persist($account);
                    $this->em->flush();
                }
            }
            catch (\Exception $e) {
                $this->sendForHelp2($e, $account);
                $this->sf->logout();
                
                return true;
            }
        }
        
        foreach ($suitcase->getItems() as $item) {
            // Item has been deleted
            if ($item->getStatus() == 'X') {
                // Item is already in SF; so we need to delete it
                if ($item->getSfId() != '') {
                    try {
                        $deleteResult = $this->sf->delete(array($item->getSfId()));
                        if ($deleteResult[0]->success) {
                            $this->em->remove($item);
                        }
                    }
                    catch (\Exception $e) {
                        $this->sendForHelp($e, $suitcase);
                        $this->sf->logout();
                        
                        return true;
                    }
                }
                else {
                    $this->em->remove($item);
                }
                $this->em->flush();
                continue;
            }
            
            
            
            $sfOpportunityLineItem = new \stdClass();
            
            // Has the item already been sync'd
            if ($item->getSfId() != '') {
                $sfOpportunityLineItem->Id = $item->getSfId();
                $new = false;
            }
            else {
                $new = true;
            }
            
            $sfOpportunityLineItem->Quantity = 1;
            $sfOpportunityLineItem->UnitPrice = $item->getPackage()->getCost();
            $sfOpportunityLineItem->Package_Status__c = 'Reserved';
            
            try {
                if ($new) {
                    $sfOpportunityLineItem->OpportunityId = $suitcase->getSfId();
                    $sfOpportunityLineItem->PricebookEntryId = $item->getPackage()->getSfPricebookEntryId();
                    $saveResult = $this->sf->create(array($sfOpportunityLineItem), 'OpportunityLineItem');
                }
                else {
                    $saveResult = $this->sf->update(array($sfOpportunityLineItem), 'OpportunityLineItem');
                }
                
                if($saveResult[0]->success) {
                    $item->setSfId($saveResult[0]->id);
                    $this->em->persist($item);
                    $this->em->flush();
                }
            }
            catch (\Exception $e) {
                $this->sendForHelp($e, $suitcase);
                $this->sf->logout();
                
                return true;
            }
        }
        
        
        
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire Reservation Confirmation')
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
                    'InertiaWinspireBundle:Email:reservation-confirmation.html.twig',
                    array(
                        'suitcase' => $suitcase,
                        'first' => $first,
                        'from' => $from
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:reservation-confirmation.txt.twig',
                    array(
                        'suitcase' => $suitcase,
                        'first' => $first,
                        'from' => $from
                    )
                ),
                'text/plain'
            )
        ;

        
        if($first) {
            $data = $this->sm->generateLoa($suitcase);
            
            $attachment = \Swift_Attachment::newInstance($data, 'Letter of Agreement.pdf', 'application/pdf');
            $message->attach($attachment);
            
            
            // Send PDF to Salesforce
            $sfAttachment = new \stdClass();
            $sfAttachment->Body = $data;
            $sfAttachment->Name = 'LOA - ' . $suitcase->getPackedAt()->format('Ymd') . '.pdf';
            $sfAttachment->ParentId = $suitcase->getSfId();
            $saveResult = $this->sf->create(array($sfAttachment), 'Attachment');
        }
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            $this->sf->logout();
            
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        $this->sf->logout();
        
        return true;
    }
    
    protected function sendForHelp(\Exception $e, $suitcase)
    {
        $message = \Swift_Message::newInstance()
        ->setSubject('Winspire::Problem during Suitcase Pack')
        ->setFrom(array('notice@winspireme.com' => 'Winspire'))
        ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
        ->setBody('Suitcase ID: ' . $suitcase->getId() . "\n" .
            'SF ID: ' . $suitcase->getSfId() . "\n" .
            'Exception: ' . $e->getMessage(),
            'text/plain'
        )
        ;
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
        
        $this->em->clear();
        $this->em->getConnection()->close();
    }
    
    protected function sendForHelp2(\Exception $e, $account)
    {
        $message = \Swift_Message::newInstance()
        ->setSubject('Winspire::Problem with Account during Suitcase Pack')
        ->setFrom(array('notice@winspireme.com' => 'Winspire'))
        ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
        ->setBody('Account ID: ' . $account->getId() . "\n" .
            'SF ID: ' . $account->getSfId() . "\n" .
            'Exception: ' . $e->getMessage(),
            'text/plain'
        )
        ;
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
        
        $this->em->clear();
        $this->em->getConnection()->close();
    }
}