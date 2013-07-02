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
            $booking = $this->em->getRepository('InertiaWinspireBundle:Booking')->findOneBySfId($id['id']);
            
            if ($booking) {
                $this->logger->info('This is a known booking; so we\'ll update');
                $bookingResult = $this->sf->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'Traveler_first_name__c, ' .
                    'Traveler_last_name__c, ' .
                    'Phone_1__c, ' .
                    'Email__c, ' .
                    'SystemModstamp ' .
                    'FROM Trip_Booking__c ' .
                    'WHERE ' .
                    'Id =\'' . $booking->getSfId() . '\''
                );
                
                // If we don't receive a Contact, then it doesn't meet the criteria
                if(count($bookingResult) == 0) {
                    $this->logger->info('Booking (' . $booking->getSfId() . ') not found in SF');
                    continue;
                }
                
                $sfBooking = $bookingResult->first();
                $booking->setFirstName($sfBooking->Traveler_first_name__c);
                $booking->setLastName($sfBooking->Traveler_last_name__c);
                $booking->setPhone($sfBooking->Phone_1__c);
                $booking->setEmail($sfBooking->Email__c);
                $booking->setSfUpdated($sfBooking->SystemModstamp);
                $booking->setSfId($sfBooking->Id);
                $this->em->persist($booking);
                $this->em->flush();
            }
            else {
                $this->logger->info('Haven\'t seen this booking before; so we have some work to do');
                if(!$suitcase) {
                    $this->logger->info('Unknown Suitcase - dropped');
                }
                else {
                    $this->logger->info('Suitcase Matched!');
                    
                }
            }
        }
        
        return array('Ack' => true);
    }
}