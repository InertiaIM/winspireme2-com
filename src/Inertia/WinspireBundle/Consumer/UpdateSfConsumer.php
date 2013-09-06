<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

class UpdateSfConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $sf;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, Client $salesforce)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->sf = $salesforce;
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        $this->sf->logout();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
        $body = unserialize($msg->body);
        $id = $body['id'];
        $type = $body['type'];
        
        switch ($type) {
            case 'suitcase':
                $query = $this->em->createQuery(
                    'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.id = :id'
                )->setParameter('id', $id);
                
                try {
                    $suitcase = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    // If we can't get the Suitcase record we'll
                    // throw out the message from the queue (ack)
                    return true;
                }
                
                if ($suitcase->getSfId() != '') {
                    $sfOpportunity = new \stdClass();
                    $sfOpportunity->Id = $suitcase->getSfId();
                    $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
                    if ($suitcase->getEventDate() != '') {
                        $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
                        $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
                    }
                    else {
                        $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
                        $sfOpportunity->CloseDate = new \DateTime('+60 days');
                    }
                    
                    $saveResult = $this->sf->update(array($sfOpportunity), 'Opportunity');
                    
                    if($saveResult[0]->success) {
                        $suitcase->setDirty(false);
                        $this->em->persist($suitcase);
                        $this->em->flush();
                    }
                }
                
                
                break;
                
            case 'suitcase-delete':
                try {
                    $sfOpportunity = new \stdClass();
                    $sfOpportunity->Id = $id;
                    $sfOpportunity->StageName = 'Lost (pre-event)';
                    $sfOpportunity->Website_suitcase_status__c = 'Deleted';
                    
                    $saveResult = $this->sf->update(array($sfOpportunity), 'Opportunity');
                    
                    if(!$saveResult[0]->success) {
                        $this->sendForHelp2('No Success', $id);
                    }
                }
                catch (\Exception $e) {
                    $this->sendForHelp2($e->getMessage(), $id);
                    $this->sf->logout();
                    
                    return true;
                }
                
                break;
                
            case 'suitcase-items':
                $query = $this->em->createQuery(
                    'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.id = :id'
                )->setParameter('id', $id);
                
                try {
                    $suitcase = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    // If we can't get the Suitcase record we'll
                    // throw out the message from the queue (ack)
                    return true;
                }
                
                // Let's just make sure the Suitcase has already been added to SF
                if ($suitcase->getSfId() == '') {
                    $sfOpportunity = new \stdClass();
                    $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
                    $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
                    $sfOpportunity->StageName = 'Counsel';
                    if ($suitcase->getEventDate() != '') {
                        $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
                        $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
                    }
                    else {
                        $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
                        $sfOpportunity->CloseDate = new \DateTime('+60 days');
                    }
                    $sfOpportunity->AccountId = $suitcase->getUser()->getCompany()->getSfId();
                    $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
                    $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
                    $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
                    $sfOpportunity->Item_Use__c = 'Silent Auction';
                    $sfOpportunity->Type = 'Web Suitcase';
                    
                    try {
                        $saveResult = $this->sf->create(array($sfOpportunity), 'Opportunity');
//echo 'talking to SF' . "\n";
                        if($saveResult[0]->success) {
                            $timestamp = new \DateTime();
                            $suitcase->setSfId($saveResult[0]->id);
                            $suitcase->setDirty(false);
                            $suitcase->setSfUpdated($timestamp);
                            $suitcase->setUpdated($timestamp);
                            $this->em->persist($suitcase);
                            $this->em->flush();
                        }
                    }
                    catch (\Exception $e) {
                        $this->sendForHelp3('Problem creating Suitcase (' . $suitcase->getId() . ')' . "\n" . $e->getMessage());
                        $this->sf->logout();
                        
                        return true;
                    }
                }
                
                $sfOpportunityLineItems = array();
                $newItems = array();
                foreach ($suitcase->getItems() as $item) {
                    // Item has been deleted
                    if ($item->getStatus() == 'X') {
                        // Item is already in SF; so we need to delete it
                        if ($item->getSfId() != '') {
                            try {
                                $deleteResult = $this->sf->delete(array($item->getSfId()));
//echo 'talking to SF' . "\n";
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
                    
                    // Has the item already been sync'd
                    if ($item->getSfId() != '') {
                        continue;
                    }
                    
                    $sfOpportunityLineItem = new \stdClass();
                    $sfOpportunityLineItem->Quantity = 1;
                    $sfOpportunityLineItem->UnitPrice = $item->getPackage()->getCost();
                    $sfOpportunityLineItem->Package_Status__c = ($suitcase->getStatus() == 'P') ? 'Reserved' : 'Interested';
                    $sfOpportunityLineItem->OpportunityId = $suitcase->getSfId();
                    $sfOpportunityLineItem->PricebookEntryId = $item->getPackage()->getSfPricebookEntryId();
                    $sfOpportunityLineItems[] = $sfOpportunityLineItem;
                    $newItems[] = $item;
                }
                
                if (count($sfOpportunityLineItems)) {
                    try {
                        $saveResult = $this->sf->create($sfOpportunityLineItems, 'OpportunityLineItem');
//echo 'talking to SF' . "\n";
                        
                        foreach ($saveResult as $index => $result) {
                            if($result->success) {
                                $newItems[$index]->setSfId($result->id);
                                $this->em->persist($newItems[$index]);
                            }
                        }
                        
                        $this->em->flush();
                    }
                    catch (\Exception $e) {
                        $this->sendForHelp($e, $suitcase);
                        $this->sf->logout();
                        
                        return true;
                    }
                }
                
                break;
                
            case 'account':
                $query = $this->em->createQuery(
                    'SELECT u, a FROM InertiaWinspireBundle:User u JOIN u.company a WHERE u.id = :id'
                )->setParameter('id', $id);
                
                try {
                    $user = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    // If we can't get the User record we'll
                    // throw out the message from the queue (ack)
                    return true;
                }
                
                $account = $user->getCompany();
                
                if ($user->getSfId() != '' && $account->getSfId() != '') {
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
                    $sfAccount->BillingCountryCode = $account->getCountry();
                    $sfAccount->BillingCountry = ($account->getCountry() == 'CA' ? 'Canada' : 'United States');
                    $sfAccount->Phone = $account->getPhone();
                    
                    try {
                        $saveResult = $this->sf->update(array($sfAccount), 'Account');
                        
                        if($saveResult[0]->success) {
                            $account->setDirty(false);
                            $this->em->persist($account);
                            $this->em->flush();
                        }
                    }
                    catch (\Exception $e) {
                        $this->sendForHelp3('Problem updating Account (' . $account->getId() . ')' . "\n" . $e->getMessage());
                        $this->sf->logout();
                        
                        return true;
                    }
                }
                
                
                break;
        }
        
        $this->sf->logout();
        $this->em->clear();
        
//        $this->mailer->getTransport()->start();
//        if (!$this->mailer->send($message)) {
//            // Any other value not equal to false will acknowledge the message and remove it
//            // from the queue
//            return false;
//        }
//        
//        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        
        return true;
    }
    
    protected function sendForHelp(\Exception $e, $suitcase)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Sync of Suitcase Items')
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
    
    protected function sendForHelp2($text, $id)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Update of Opportunity to LOST')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
            ->setBody(
                'SF ID: ' . $id . "\n" .
                'Extra Text: ' . $text,
                'text/plain'
            )
        ;
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
        
        $this->em->clear();
        $this->em->getConnection()->close();
    }
    
    protected function sendForHelp3($text)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Sync to SF')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
            ->setBody($text,
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