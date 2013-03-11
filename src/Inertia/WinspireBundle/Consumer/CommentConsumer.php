<?php
namespace Inertia\WinspireBundle\Consumer;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class CommentConsumer implements ConsumerInterface
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
        $commentId = $body['comment_id'];
        
        $query = $this->em->createQuery(
            'SELECT c FROM InertiaWinspireBundle:Comment c WHERE c.id = :id'
        )->setParameter('id', $commentId);
        
        try {
            $comment = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
//            throw $this->createNotFoundException();
        }
        
        $name = $comment->getSuitcase()->getUser()->getFirstName() . ' ' .
            $comment->getSuitcase()->getUser()->getLastName();
        
        $email = $comment->getSuitcase()->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('New Comment in your Suitcase')
            ->setFrom('notice@winspireme.com')
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:comment-notification.html.twig',
                    array('comment' => $comment)
                ),
                'text/html'
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