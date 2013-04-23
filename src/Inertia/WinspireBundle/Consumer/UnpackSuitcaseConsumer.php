<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class UnpackSuitcaseConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, Client $salesforce)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->sf = $salesforce;
        
        $this->mailer->getTransport()->stop();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $body = unserialize($msg->body);
        $suitcaseId = $body['suitcase_id'];
        
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
        
        // TODO temp fix for abnormal account/suitcases flagged
        if ($account->getSfId() != 'TEST' && $account->getSfId() != 'CANADA' && $account->getSfId() != 'PARTNER') {
        // Salesforce Updates
        $sfOpportunity = new \stdClass();
        $sfOpportunity->Name = $suitcase->getName();
        $sfOpportunity->StageName = 'Counsel';
        $sfOpportunity->fieldsToNull = array('Date_converted_to_Reservation__c');
        $sfOpportunity->Event_Name__c = $suitcase->getEventName();
        if ($suitcase->getEventDate() != '') {
            $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
            $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
        }
        else {
            $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
            $sfOpportunity->CloseDate = new \DateTime('+60 days');
        }
        $sfOpportunity->AccountId = $account->getSfId();
        $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
        $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
        $sfOpportunity->Type = 'Web Suitcase';
        $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
        $sfOpportunity->Item_Use__c = 'Silent Auction';
        
        if ($suitcase->getSfId() == '') {
            // We haven't done an initial sync of the Suitcase?
            // Probably not even possible, but just in case
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
        
        foreach ($suitcase->getItems() as $item) {
            // Item has been deleted
            if ($item->getStatus() == 'X') {
                // Item is already in SF; so we need to delete it
                if ($item->getSfId() != '') {
                    $deleteResult = $this->sf->delete(array($item->getSfId()));
                    if ($deleteResult[0]->success) {
                        $this->em->remove($item);
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
        }
        
        
        
        
        
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Please confirm changes to your Suitcase')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:suitcase-unpacked.html.twig',
                    array('suitcase' => $suitcase)
                ),
                'text/html'
            )
        ;
        $message->setBcc($account->getSalesperson()->getEmail());
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        return true;
    }
}