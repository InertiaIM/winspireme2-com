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
                    
                    $saveResult = $this->sf->update(array($sfAccount), 'Account');
                    
                    if($saveResult[0]->success) {
                        $account->setDirty(false);
                        $this->em->persist($account);
                        $this->em->flush();
                    }
                }
                
                
                break;
        }
        
        
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
}