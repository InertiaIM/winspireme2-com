<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;

class BookingSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    
    private $recordTypeId = '01270000000DVD5AAO';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('Here comes a Trip Booking update...');
        
        if(!isset($notifications->Notification)) {
            $this->logger->info('notification object is bogus');
            exit;
        }
        
        $ids = array();
        
        if(!is_array($notifications->Notification)) {
            $ids[] = array(
                'id' => $notifications->Notification->sObject->Id, 
                'suitcase' => $notifications->Notification->sObject->Opportunity__c
            );
        }
        else {
            foreach($notifications->Notification as $n) {
                $ids[] = array(
                    'id' => $n->sObject->Id, 
                    'suitcase' => $n->sObject->Opportunity__c
                );
            }
        }
        
        foreach($ids as $id) {
            $this->logger->info('Trip Booking Id: ' . $id['id']);
            $this->logger->info('Suitcase Id: ' . $id['suitcase']);
            
            
            // Test whether this suitcase (opportunity) is already in our database
            $suitcase = $this->em->getRepository('InertiaWinspireBundle:Suitcase')->findOneBySfId($id['suitcase']);
            
            if(!$suitcase) {
                $this->logger->info('Unknown Suitcase - dropped');
            }
            else {
                $this->logger->info('Suitcase Matched!');
            }
        }
        
        return array('Ack' => true);
    }
}