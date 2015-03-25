<?php
namespace Inertia\WinspireBundle\Consumer;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class ShareSuitcaseConsumer implements ConsumerInterface
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
        $shareId = $body['share_id'];
        
        $query = $this->em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Share s WHERE s.id = :id'
        )->setParameter('id', $shareId);
        
        try {
            $share = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
//            throw $this->createNotFoundException();
        }
        
        $locale = strtolower($share->getSuitcase()->getUser()->getCompany()->getCountry());
        
        $message = \Swift_Message::newInstance()
            ->setSubject('You have been invited to view a Suitcase')
            ->setSender(array('info@winspireme.com' => 'Winspire'))
            ->setReplyTo(array(
                $share->getSuitcase()->getUser()->getEmail() =>
                $share->getSuitcase()->getUser()->getFirstName() . ' ' .
                $share->getSuitcase()->getUser()->getLastName()
            ))
            // ->setFrom(array(
            //    $share->getSuitcase()->getUser()->getEmail() =>
            //    $share->getSuitcase()->getUser()->getFirstName() . ' ' .
            //    $share->getSuitcase()->getUser()->getLastName()
            //))
            ->setFrom(array('noreply@winspireme.com' => 'Winspire'))
            ->setTo(array($share->getEmail() => $share->getName()))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:guest-invitation.html.twig',
                    array(
                        'share' => $share,
                        //'from' => $share->getSuitcase()->getUser()->getEmail(),
                        'from' => 'noreply@winspireme.com',
                        'locale' => $locale,
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:guest-invitation.txt.twig',
                    array(
                        'share' => $share,
                        //'from' => $share->getSuitcase()->getUser()->getEmail(),
                        'from' => 'noreply@winspireme.com',
                        'locale' => $locale,
                    )
                ),
                'text/plain'
            )
        ;
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        if (!$this->mailer->send($message)) {
            // Any other value not equal to false will acknowledge the message and remove it
            // from the queue
            return false;
        }
        
        $this->mailer->getTransport()->stop();
        return true;
    }
}