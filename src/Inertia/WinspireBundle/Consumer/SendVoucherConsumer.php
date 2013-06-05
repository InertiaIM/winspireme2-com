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
        $message = $body['message'];
        
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
        
        $name = $booking->getFirstName() . ' ' .
            $booking->getLastName();
        
        $email = $booking->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Use this Booking Voucher to redeem your Experience!')
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:booking-voucher.html.twig',
                    array(
                        'booking' => $booking,
                        'suitcase' => $suitcase,
                        'message' => $message
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
                        'message' => $message
                    )
                ),
                'text/plain'
            )
        ;
//        $message->setBcc($account->getSalesperson()->getEmail());
        $message->setBcc('doug@inertiaim.com');
        
        if ($cc) {
            $message->setBcc('doug@dfector.com');
        }
        
        
        $booking->setVoucherSentAt(new \DateTime());
        $this->em->persist($booking);
        $this->em->flush();
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        $this->em->getConnection()->close();
        
        return true;
    }
}