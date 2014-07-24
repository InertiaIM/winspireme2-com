<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class CreateSuitcaseConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $producer;
    protected $templating;
    protected $sf;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating, Client $salesforce, Producer $producer)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->producer = $producer;
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
            $sfAccount->Event_Type_Unknown__c = true;
            if ($suitcase->getEventDate() != '') {
                $sfAccount->Event_Month_Unknown__c = $suitcase->getEventDate()->format('F');
            }
            else {
                $temp = new \DateTime('+30 days');
                $sfAccount->Event_Month_Unknown__c = $temp->format('F');
            }
            $sfAccount->Item_Use__c = 'Unknown';
            
            try {
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
            catch (\Exception $e) {
                $this->sendForHelp('Problem creating Account (' . $account->getId() . ')' . "\n" . $e->getMessage());
                $this->sf->logout();
                
                return true;
            }
        }
        else {
            $sfAccount = new \stdClass();
            $sfAccount->Id = $account->getSfId();
            if ($suitcase->getEventDate() != '') {
                $sfAccount->Event_Month_Unknown__c = $suitcase->getEventDate()->format('F');
            }
            else {
                $temp = new \DateTime('+30 days');
                $sfAccount->Event_Month_Unknown__c = $temp->format('F');
            }
            
            $saveResult = $this->sf->update(array($sfAccount), 'Account');
            
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
            $sfContact->LeadSource = 'TBD';
            
            try {
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
            catch (\Exception $e) {
                $this->sendForHelp('Problem creating Contact (' . $user->getId() . ')' . "\n" . $e->getMessage());
                $this->sf->logout();
                
                return true;
            }
        }
        
        if ($user->getSfId() != '' && $account->getSfId() != '') {
            $sfContact = new \stdClass();
            $sfContact->Id = $user->getSfId();
            
            $saveResult = $this->sf->update(array($sfContact), 'Contact');
            
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
            $sfOpportunity->LeadSource = 'TBD';
            $sfOpportunity->Type = 'Web Suitcase';
            $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
            $sfOpportunity->Item_Use__c = 'Unknown';
            $sfOpportunity->Event_Type__c = 'Unknown';
            
            try {
                $saveResult = $this->sf->create(array($sfOpportunity), 'Opportunity');
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $suitcase->setSfId($saveResult[0]->id);
                    $suitcase->setDirty(false);
                    $suitcase->setSfUpdated($timestamp);
                    $suitcase->setUpdated($timestamp);
                    $this->em->persist($suitcase);
                    $this->em->flush();
                    
                    $msg = array('id' => $suitcase->getId(), 'type' => 'suitcase-items');
                    $this->producer->publish(serialize($msg), 'update-sf');
                    
                    $this->sendMessage($suitcase);
                }
            }
            catch (\Exception $e) {
                $this->sendForHelp('Problem creating Suitcase (' . $suitcase->getId() . ')' . "\n" . $e->getMessage());
                $this->sf->logout();
                
                return true;
            }
        }
        
        $this->em->clear();
        $this->sf->logout();
        
        return true;
    }
    
    protected function sendMessage($suitcase)
    {
        // Send Mail Messages
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();

        $email = $suitcase->getUser()->getEmail();
        
        $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());

        $message = \Swift_Message::newInstance()
            ->setSender(array('info@winspireme.com' => 'Winspire'))
            ->setSubject('Your New Suitcase is Ready!')
            ->setTo(array($email => $name))
            ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
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
                    'InertiaWinspireBundle:Email:new-suitcase-confirm.html.twig',
                    array(
                        'suitcase' => $suitcase,
                        'from' => $from,
                        'locale' => $locale,
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:new-suitcase-confirm.txt.twig',
                    array(
                        'suitcase' => $suitcase,
                        'from' => $from,
                        'locale' => $locale,
                    )
                ),
                'text/plain'
            )
        ;
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
    }
    
    protected function sendForHelp($text)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Debug for Create Suitcase')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
            ->setBody(
                $text,
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