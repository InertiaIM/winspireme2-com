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
        $this->sf->logout();
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
        
        // Salesforce Updates
        $sfOpportunity = new \stdClass();
        $sfOpportunity->Name = $suitcase->getName();
        $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
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
                // Probably not even possible, but just in case
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
        
        $this->em->clear();
        $this->sf->logout();
        
        return true;
    }
    
    protected function sendForHelp(\Exception $e, $suitcase)
    {
        $message = \Swift_Message::newInstance()
        ->setSubject('Winspire::Problem during Unpack Request')
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