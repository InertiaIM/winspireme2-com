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
    protected $fee;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, Client $salesforce, $fee)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->sf = $salesforce;
        $this->fee = $fee;
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        $this->sf->logout();
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
            $this->sf->logout();
            
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
                    $suitcase->setSfContactRoleId($saveResult[0]->id);
                    $this->em->persist($suitcase);
                    $this->em->flush();
                }
            }
            catch(\Exception $e) {
                $this->sendForHelp($e, $suitcase);
                $this->sf->logout();
                
                return true;
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
                if ($item->getSfId() != '' && $suitcase->getStatus() != 'M') {
                    $deleteResult = $this->sf->delete(array($item->getSfId()));
                    if ($deleteResult[0]->success) {
                        $item->setSfId(null);
                        $this->em->persist($item);
                        $this->em->flush();
                    }
                }
                
                if ($suitcase->getStatus() != 'M') {
                    continue;
                }
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
            
            if ($suitcase->getStatus() == 'M') {
                $sfOpportunityLineItem->Quantity = 1;
                $sfOpportunityLineItem->Package_Status__c = 'Reserved';
            }
            else {
                $sfOpportunityLineItem->Quantity = $item->getQuantity();
                $sfOpportunityLineItem->Package_Status__c = 'Sold';
            }
            
            $sfOpportunityLineItem->UnitPrice = $item->getCost();
            
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
                else {
                    throw new Exception('SF didn\'t save an Opportunity Line Item');
                }
            }
            catch(\Exception $e) {
                $this->sendForHelp($e, $suitcase);
                $this->sf->logout();
                
                return true;
            }
        }
        
        
        // Update Suitcase -> Opportunity
        $sfOpportunity = new \stdClass();
        $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
        
        if ($suitcase->getStatus() == 'M') {
            $sfOpportunity->StageName = 'Lost (No Bids)';
            $sfOpportunity->Website_suitcase_status__c = 'Missed';
        }
        else {
            $sfOpportunity->StageName = 'Sold Items';
            $sfOpportunity->Website_suitcase_status__c = 'Inv. Req.';
        }
        $sfOpportunity->LOA_Received__c = 1;
        $sfOpportunity->Event_Name__c = substr($suitcase->getEventName(), 0, 40);
        $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
        $sfOpportunity->CloseDate = $suitcase->getInvoiceRequestedAt();
        $sfOpportunity->AccountId = $account->getSfId();
        
        try {
            if ($suitcase->getSfId() == '') {
                // We haven't done an initial sync of the Suitcase? Is this even possible at this stage?
                $sfOpportunity->Type = 'Web Suitcase';
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
                throw new Exception('SF didn\'t save the Opportunity');
            }
        }
        catch(\Exception $e) {
            $this->sendForHelp($e, $suitcase);
            $this->sf->logout();
            
            return true;
        }
        
        
        $subtotal = 0;
        foreach ($suitcase->getItems() as $item) {
            $subtotal += $item->getSubtotal();
        }
        
        $fee = $this->fee;
        
        if ($subtotal > 0) {
            $grandTotal = $fee + $subtotal;
        }
        else {
            $grandTotal = 0;
        }
        
        
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        
        if ($suitcase->getStatus() == 'M') {
            $name = 'No Sells';
            $subject = 'Thank you for confirming your results.';
            $template = 'no-items-sold-invoice.html.twig';
        }
        else {
            $name = 'Invoice Request';
            $subject = 'Thank you for requesting an invoice';
            $template = 'invoice-requested.html.twig';
        }
        
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:' . $template,
                    array(
                        'suitcase' => $suitcase,
                        'subtotal' => $subtotal,
                        'fee' => $fee,
                        'grand_total' => $grandTotal
                    )
                ),
                'text/html'
            )
        ;
        $message->setBcc(array($account->getSalesperson()->getEmail(), 'doug@inertiaim.com'));
        
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            $this->sf->logout();
            
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        
        
        // Send HTML to Salesforce
        $html = $this->templating->render(
            'InertiaWinspireBundle:Email:' . $template,
            array(
                'suitcase' => $suitcase,
                'subtotal' => $subtotal,
                'fee' => $fee,
                'grand_total' => $grandTotal
            )
        );
        $sfAttachment = new \stdClass();
        $sfAttachment->Body = $html;
        $sfAttachment->Name = $name . ' - ' . $suitcase->getInvoiceRequestedAt()->format('Ymd') . '.html';
        $sfAttachment->ParentId = $suitcase->getSfId();
        $saveResult = $this->sf->create(array($sfAttachment), 'Attachment');
        
        
        $this->em->getConnection()->close();
        $this->sf->logout();
        
        return true;
    }
    
    protected function sendForHelp(\Exception $e, $suitcase)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Invoice Request')
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
}