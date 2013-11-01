<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class BookingUpdateConsumer implements ConsumerInterface
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
        $this->em->getConnection()->close();
        $this->sf->logout();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
        $body = unserialize($msg->body);
        $bookingId = $body['booking_id'];
        
        $query = $this->em->createQuery(
            'SELECT b, i, p, s FROM InertiaWinspireBundle:Booking b JOIN b.suitcaseItem i JOIN i.package p JOIN i.suitcase s WHERE b.id = :id'
        )->setParameter('id', $bookingId);
        
        try {
            $booking = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // If we can't get the Suitcase record we'll 
            // throw out the message from the queue (ack)
            return true;
        }
        
        $suitcase = $booking->getSuitcaseItem()->getSuitcase();
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        // If we don't already have an sf_id, then we have some work to do.
        if ($booking->getSfId() == '') {
            // How many other Trip Booking records am I competing with for a match in SF?
            $query = $this->em->createQuery(
                'SELECT b, i FROM InertiaWinspireBundle:Booking b JOIN b.suitcaseItem i WHERE b.id != :id AND i.id = :item_id'
            )
                ->setParameter('id', $bookingId)
                ->setParameter('item_id', $booking->getSuitcaseItem()->getId())
            ;
            
            $otherBookings = $query->getResult();
            $otherBookingIds = array();
            foreach ($otherBookings as $b) {
                if ($b->getSfId() != '') {
                    $otherBookingIds[] = '\'' . $b->getSfId() . '\'';
                }
            }
            
            
            if (count($otherBookingIds) > 0) {
                $TripBookingResult = $this->sf->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'Traveler_first_name__c, ' .
                    'Traveler_last_name__c, ' .
                    'Phone_1__c, ' .
                    'Email__c, ' .
                    'TB_Booking_type__c, ' .
                    'SystemModstamp ' .
                    'FROM Trip_Booking__c ' .
                    'WHERE ' .
                    'Opportunity__r.Id = \'' . $suitcase->getSfId() . '\' ' .
                    'AND Package__r.Id = \'' . $booking->getSuitcaseItem()->getPackage()->getSfId() . '\' ' .
                    'AND Id NOT IN (' . implode(', ', $otherBookingIds) . ')'
                );
            }
            else {
                $TripBookingResult = $this->sf->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'Traveler_first_name__c, ' .
                    'Traveler_last_name__c, ' .
                    'Phone_1__c, ' .
                    'Email__c, ' .
                    'TB_Booking_type__c, ' .
                    'SystemModstamp ' .
                    'FROM Trip_Booking__c ' .
                    'WHERE ' .
                    'Opportunity__r.Id = \'' . $suitcase->getSfId() . '\' ' .
                    'AND Package__r.Id = \'' . $booking->getSuitcaseItem()->getPackage()->getSfId() . '\' '
                );
            }
            
            if (count($TripBookingResult) > 0) {
                // We'll just grab the first available result
                $sfBooking = $TripBookingResult->first();
                
                $sfBookingUpdate = new \stdClass();
                $sfBookingUpdate->Id = $sfBooking->Id;
                $sfBookingUpdate->Traveler_first_name__c = substr($booking->getFirstName(), 0, 25);
                $sfBookingUpdate->Traveler_last_name__c = substr($booking->getLastName(), 0, 25);
                $sfBookingUpdate->Phone_1__c = substr($booking->getPhone(), 0, 40);
                $sfBookingUpdate->Email__c = $booking->getEmail();
                
                $saveResult = $this->sf->update(array($sfBookingUpdate), 'Trip_Booking__c');
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $booking->setCertificateId($sfBooking->TB_Booking_type__c);
                    $booking->setSfId($saveResult[0]->id);
                    $booking->setDirty(false);
                    $booking->setSfUpdated($timestamp);
                    $booking->setUpdated($timestamp);
                    $this->em->persist($booking);
                    $this->em->flush();
                }
            }
            else {
                // If the results are empty, we're in trouble.
                // This is the end of the line; we should never reach this point.
                // We'll send an SOS email, and drop the item from the message queue.
                $message = \Swift_Message::newInstance()
                    ->setSubject('Problem sending booking data to SF')
                    ->setFrom(array('info@winspireme.com' => 'Winspire'))
                    ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
                    ->setBody('Problem Booking ID: ' . $booking->getId(), 'text/plain')
                ;
                
                $this->mailer->getTransport()->start();
                $this->mailer->send($message);
                $this->mailer->getTransport()->stop();
                
                $this->em->clear();
                $this->em->getConnection()->close();
                
                return true;
            }
            
        }
        // Otherwise, it's a simple update.
        else {
            $sfBookingUpdate = new \stdClass();
            $sfBookingUpdate->Id = $booking->getSfId();
            $sfBookingUpdate->Traveler_first_name__c = substr($booking->getFirstName(), 0, 25);
            $sfBookingUpdate->Traveler_last_name__c = substr($booking->getLastName(), 0, 25);
            $sfBookingUpdate->Phone_1__c = substr($booking->getPhone(), 0, 40);
            $sfBookingUpdate->Email__c = $booking->getEmail();
            
            $saveResult = $this->sf->update(array($sfBookingUpdate), 'Trip_Booking__c');
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $booking->setSfId($saveResult[0]->id);
                $booking->setDirty(false);
                $booking->setSfUpdated($timestamp);
                $booking->setUpdated($timestamp);
                $this->em->persist($booking);
                $this->em->flush();
            }
        }
        
//        $message = \Swift_Message::newInstance()
//            ->setSubject('An update has posted to a Trip Booking')
//            ->setFrom(array('info@winspireme.com' => 'Winspire'))
//            ->setTo($account->getSalesperson()->getEmail())
//            ->setBody('Opportunity: ' . $suitcase->getName() . "\n" . 
//                'Trip Booking: ' . $booking->getCertificateId() . "\n\n" .
//                'https://na5.salesforce.com/' . $booking->getSfId(),
//                'text/plain'
//            )
//        ;
        
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
}