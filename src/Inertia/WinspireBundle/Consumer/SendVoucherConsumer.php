<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class SendVoucherConsumer implements ConsumerInterface
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
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
        $body = unserialize($msg->body);
        $bookingId = $body['booking_id'];
        $cc = $body['cc'];
        $customMessage = $body['message'];
        
        $query = $this->em->createQuery(
            'SELECT b, i, p, s FROM InertiaWinspireBundle:Booking b JOIN b.suitcaseItem i JOIN i.package p JOIN i.suitcase s WHERE b.id = :id AND s.status = \'A\''
        )->setParameter('id', $bookingId);
        
        try {
            $booking = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // If we can't get the Booking record we'll 
            // throw out the message from the queue (ack)
            return true;
        }
        
        $suitcase = $booking->getSuitcaseItem()->getSuitcase();
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        $locale = strtolower($account->getCountry());
        
        $name = $booking->getFirstName() . ' ' .
            $booking->getLastName();
        
        $email = $booking->getEmail();
        
        
        // Query for appropriate Content Pack Version
        $query = $this->em->createQuery(
            'SELECT c, v FROM InertiaWinspireBundle:ContentPack c JOIN c.versions v WHERE c.sfId = :id AND v.created <= :date ORDER BY v.created DESC'
        )
            ->setParameter('id', $booking->getSuitcaseItem()->getPackage()->getSfContentPackId())
            ->setParameter('date', $suitcase->getEventDate())
        ;
        
        $query->setMaxResults(1);
        
        try {
            $contentPack = $query->getSingleResult();
            $contentPackVersions = $contentPack->getVersions();
            $contentPackVersion = $contentPackVersions[0];
            $contentPackVersionId = $contentPackVersion->getId();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $contentPackVersionId = false;
        }
        
        
        
        
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Use this Booking Voucher to redeem your Experience!')
            ->setReplyTo(array($user->getEmail() => $user->getFirstName() . ' ' . $user->getLastName()))
            ->setSender(array('info@winspireme.com' => 'Winspire'))
            ->setFrom(array($user->getEmail() => $user->getCompany()->getName()))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:booking-voucher.html.twig',
                    array(
                        'booking' => $booking,
                        'suitcase' => $suitcase,
                        'message' => $customMessage,
                        'content_pack_version_id' => $contentPackVersionId,
                        'locale' => $locale,
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:booking-voucher.txt.twig',
                    array(
                        'booking' => $booking,
                        'suitcase' => $suitcase,
                        'message' => $customMessage,
                        'content_pack_version_id' => $contentPackVersionId,
                        'locale' => $locale,
                    )
                ),
                'text/plain'
            )
        ;
        $message->setBcc(array($account->getSalesperson()->getEmail()));
        
        if ($cc) {
            $message->setBcc(array($suitcase->getUser()->getEmail(), $account->getSalesperson()->getEmail()));
        }
        
        
        $booking->setVoucherSentAt(new \DateTime());
        $this->em->persist($booking);
        $this->em->flush();
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
        
        
        // Update the Trip Booking record in SF after the Voucher is sent
        try {
            $sfBooking = new \stdClass();
            $sfBooking->Id = $booking->getSfId();
            $sfBooking->voucher_emailed__c = true;
            $saveResult = $this->sf->update(array($sfBooking), 'Trip_Booking__c');
            
            
            $html = $this->templating->render(
                'InertiaWinspireBundle:Email:booking-voucher.html.twig',
                array(
                    'booking' => $booking,
                    'suitcase' => $suitcase,
                    'message' => $customMessage,
                    'content_pack_version_id' => $contentPackVersionId,
                    'locale' => $locale
                )
            );
            
            // Send HTML to Salesforce
            $sfAttachment = new \stdClass();
            $sfAttachment->Body = $html;
            $sfAttachment->Name = 'Voucher - ' . $booking->getVoucherSentAt()->format('Ymd') . '.html';
            $sfAttachment->ParentId = $booking->getSfId();
            $saveResult = $this->sf->create(array($sfAttachment), 'Attachment');
        }
        catch (\Exception $e) {
            $this->sendForHelp($e, $booking);
        }
        
        $this->sf->logout();
        $this->em->getConnection()->close();
        
        return true;
    }
    
    protected function sendForHelp(\Exception $e, $booking)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Problem during Voucher send')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('iim@inertiaim.com' => 'Inertia-IM'))
            ->setBody('Booking ID: ' . $booking->getId() . "\n" .
                'SF ID: ' . $booking->getSfId() . "\n" .
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