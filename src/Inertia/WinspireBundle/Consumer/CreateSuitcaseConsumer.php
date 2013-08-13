<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class CreateSuitcaseConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    protected $sf;
    
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
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.id = :id ORDER BY i.updated DESC'
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
        
        
        // Salesforce Updates
        $account = $user->getCompany();
        if ($account->getSfId() == '') {
            $address = $account->getAddress();
            if ($account->getAddress2() != '') {
                $address .= chr(10) . $account->getAddress2();
            }
            
            $sfAccount = new \stdClass();
            $sfAccount->Name = $account->getName();
            $sfAccount->BillingStreet = $address;
            $sfAccount->BillingCity = $account->getCity();
            $sfAccount->BillingState = $account->getState();
            $sfAccount->BillingPostalCode = $account->getZip();
            $sfAccount->Phone = $account->getPhone();
            $sfAccount->Referred_by__c = $account->getReferred();
            $sfAccount->RecordTypeId = $this->recordTypeId;
            $sfAccount->OwnerId = $account->getSalesperson()->getSfId();
            
            $saveResult = $this->sf->create(array($sfAccount), 'Account');
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $account->setSfId($saveResult[0]->id);
                $account->setDirty(false);
                $account->setSfUpdated($timestamp);
                $account->setUpdated($timestamp);
                $this->em->persist($account);
                $this->em->flush();
            }
        }
        
        if ($user->getSfId() == '' && $account->getSfId() != '') {
            $sfContact = new \stdClass();
            $sfContact->FirstName = $user->getFirstName();
            $sfContact->LastName = $user->getLastName();
            $sfContact->Phone = $user->getPhone();
            $sfContact->Email = $user->getEmail();
            $sfContact->AccountId = $account->getSfId();
            $sfContact->Default_contact__c = 1;
            $sfContact->OwnerId = $account->getSalesperson()->getSfId();
            
            $saveResult = $this->sf->create(array($sfContact), 'Contact');
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $user->setSfId($saveResult[0]->id);
                $user->setDirty(false);
                $user->setSfUpdated($timestamp);
                $user->setUpdated($timestamp);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
        
        if ($suitcase->getSfId() == '' && $account->getSfId() != '') {
            $sfOpportunity = new \stdClass();
            $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . ' +30 days');
            $sfOpportunity->Name = $suitcase->getName();
            $sfOpportunity->StageName = 'Counsel';
            $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
            $sfOpportunity->Event_Name__c = $suitcase->getName();
            $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
            $sfOpportunity->AccountId = $account->getSfId();
            $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
            $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
            $sfOpportunity->Type = 'Web Suitcase';
            $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
            $sfOpportunity->Item_Use__c = 'Silent Auction';
            
            $saveResult = $this->sf->create(array($sfOpportunity), 'Opportunity');
            
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
        
        
        // Send Mail Messages
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Your New Suitcase is Ready!')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBcc($suitcase->getUser()->getCompany()->getSalesperson()->getEmail())
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:new-suitcase-confirm.html.twig',
                    array('suitcase' => $suitcase)
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:new-suitcase-confirm.txt.twig',
                    array('suitcase' => $suitcase)
                ),
                'text/plain'
            )
        ;
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            $this->sf->logout();
            
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        
        $this->sf->logout();
        
        return true;
    }
}