<?php
namespace Inertia\WinspireBundle\Consumer;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class ContactConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    
    public function __construct(EntityManager $entityManager, \Swift_Mailer $mailer, EngineInterface $templating)
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        
        $this->mailer->getTransport()->stop();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $body = unserialize($msg->body);
        
        $topic = $body['topic'];
        $first = $body['first'];
        $last = $body['last'];
        $organization = $body['organization'];
        $phone = $body['phone'];
        $email = $body['email'];
        $comments = $body['comments'];
        
        $recipient = '';
        $subject = '';
        switch($topic) {
            case 'new-customer':
                $recipient = 'suitcase@winspireme.com';
                $subject = 'New Customer';
                break;
            case 'reserving-experiences':
                $recipient = 'suitcase@winspireme.com';
                $subject = 'Reserving Experiences';
                break;
            case 'offering-experiences':
                $recipient = 'suitcase@winspireme.com';
                $subject = 'Offering Experiences';
                break;
            case 'event-support':
                $recipient = 'suitcase@winspireme.com';
                $subject = 'Event Support';
                break;
            case 'redeeming':
                $recipient = 'ops@winspireme.com';
                $subject = 'Redeeming & Booking Experiences';
                break;
            case 'payment':
                $recipient = 'ops@winspireme.com';
                $subject = 'Payment & Invoicing';
                break;
            case 'nonprofit-testimonial':
                $recipient = 'testimonial@winspireme.com';
                $subject = 'Nonprofit Testimonial';
                break;
            case 'winning-bidder-testimonial':
                $recipient = 'testimonial@winspireme.com';
                $subject = 'Winning Bidder Testimonial';
                break;
            case 'learn-more':
                $recipient = 'info@winspireme.com';
                $subject = 'Learn More About Winspire';
                break;
            case 'learn-more':
                $recipient = 'info@winspireme.com';
                $subject = 'Learn More About Winspire';
                break;
            case 'partners':
                $recipient = 'info@winspireme.com';
                $subject = 'Partners';
                break;
            case 'referral':
                $recipient = 'info@winspireme.com';
                $subject = 'Referral';
                break;
            case 'other':
                $recipient = 'info@winspireme.com';
                $subject = 'Other';
                break;
        }
        
        $name = $first . ' ' . $last;
        
        
        // Email to the user
        $message = \Swift_Message::newInstance()
            ->setSubject('We\'ve received your message')
            ->setFrom('notice@winspireme.com')
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:contact-response.html.twig',
                    array()
                ),
                'text/html'
            )
        ;
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        // Email to the Winspire staff
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire Contact Form Submission')
            ->setFrom(array($email => $name))
            ->setTo($recipient)
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:contact-submit.html.twig',
                    array(
                        'name' => $name,
                        'email' => $email,
                        'organization' => $organization,
                        'phone' => $phone,
                        'subject' => $subject,
                        'comments' => $comments
                    )
                ),
                'text/html'
            )
        ;
        
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        
        $this->mailer->getTransport()->stop();
        return true;
    }
}