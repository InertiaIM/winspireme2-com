<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class InvoiceRequestConsumer implements ConsumerInterface
{
    protected $accountingEmail;
    protected $em;
    protected $mailer;
    protected $templating;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, Client $salesforce, $email)
    {
        $this->accountingEmail = $email;
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->sf = $salesforce;
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
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
        
        // Make sure we have our user setup as an OpportunityContactRole
        if ($user->getSfId() != '' && $suitcase->getSfId() != '' && $suitcase->getSfContactRoleId() == '') {
            $sfOpportunityContactRole = new \stdClass();
            $sfOpportunityContactRole->ContactId = $user->getSfId();
            $sfOpportunityContactRole->IsPrimary = true;
            $sfOpportunityContactRole->OpportunityId = $suitcase->getSfId();
            $sfOpportunityContactRole->Role = 'Website user';
            
            $saveResult = $this->sf->create(array($sfOpportunityContactRole), 'OpportunityContactRole');
            
            if($saveResult[0]->success) {
                $suitcase->setSfContactRoleId($saveResult[0]->id);
                $this->em->persist($suitcase);
                $this->em->flush();
            }
        }
        
        // Update all Suitcase Items -> Opportunity Items
        foreach ($suitcase->getItems() as $item) {
            // Item has been deleted from Suitcase
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
            
            // If item didn't sell, we can't keep it in the Opportunity (due to field constraints)
            if ($item->getQuantity() == 0) {
                // If it's already in SF, we have to remove it.
                if ($item->getSfId() != '') {
                    $deleteResult = $this->sf->delete(array($item->getSfId()));
                    if ($deleteResult[0]->success) {
                        $item->setSfId(null);
                        $this->em->persist($item);
                        $this->em->flush();
                    }
                }
                continue;
            }
            
            $sfOpportunityLineItem = new \stdClass();
            
            // Has the item already been sync'd before?
            if ($item->getSfId() != '') {
                $sfOpportunityLineItem->Id = $item->getSfId();
                $new = false;
            }
            else {
                $new = true;
            }
            
            $sfOpportunityLineItem->Quantity = $item->getQuantity();
            $sfOpportunityLineItem->UnitPrice = $item->getCost();
            $sfOpportunityLineItem->Package_Status__c = 'Sold';
            
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
        
        
        // Update Suitcase -> Opportunity
        $sfOpportunity = new \stdClass();
        $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
        $sfOpportunity->Website_suitcase_status__c = 'Packed';
        $sfOpportunity->StageName = 'Sold Items';
        $sfOpportunity->LOA_Received__c = 1;
        $sfOpportunity->Event_Name__c = substr($suitcase->getEventName(), 0, 40);
        $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
        $sfOpportunity->CloseDate = $suitcase->getInvoiceRequestedAt();
        $sfOpportunity->AccountId = $account->getSfId();
        $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
        $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
        $sfOpportunity->Type = 'Web Suitcase';
        $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
        $sfOpportunity->Item_Use__c = 'Silent Auction';
        
        if ($suitcase->getSfId() == '') {
            // We haven't done an initial sync of the Suitcase? Is this even possible at this stage?
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
            $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Invoice Request')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
            ->setBody('Suitcase ID: ' . $suitcase->getId() . "\n" .
                'SF ID: ' . $suitcase->getSfId(),
                'text/plain'
            );
            
            $this->mailer->getTransport()->start();
            $this->mailer->send($message);
        }
        
        $grandTotal = 0;
        foreach ($suitcase->getItems() as $item) {
            $grandTotal += $item->getSubtotal();
        }
        
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Thank you for requesting an invoice')
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:invoice-requested.html.twig',
                    array(
                        'suitcase' => $suitcase,
                        'grand_total' => $grandTotal
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:invoice-requested.txt.twig',
                    array(
                        'suitcase' => $suitcase,
                        'grand_total' => $grandTotal
                    )
                ),
                'text/plain'
            )
        ;
//        $message->setBcc($account->getSalesperson()->getEmail());
        $message->setBcc('doug@inertiaim.com');
        
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        
        
        // Send HTML to Salesforce
        $html = $this->templating->render(
            'InertiaWinspireBundle:Email:invoice-requested.html.twig',
            array(
                'suitcase' => $suitcase,
                'grand_total' => $grandTotal
            )
        );
        $sfAttachment = new \stdClass();
        $sfAttachment->Body = $html;
        $sfAttachment->Name = 'Invoice Request - ' . $suitcase->getInvoiceRequestedAt()->format('Ymd') . '.html';
        $sfAttachment->ParentId = $suitcase->getSfId();
        $saveResult = $this->sf->create(array($sfAttachment), 'Attachment');
        
        
        $this->em->getConnection()->close();
        
        return true;
    }
}