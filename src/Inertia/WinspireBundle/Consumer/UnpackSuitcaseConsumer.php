<?php
namespace Inertia\WinspireBundle\Consumer;

use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class UnpackSuitcaseConsumer implements ConsumerInterface
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
        $suitcaseId = $body['suitcase_id'];
        
        $query = $this->em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.id = :id ORDER BY i.updated DESC'
        )->setParameter('id', $suitcaseId);
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
//            throw $this->createNotFoundException();
        }
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Please confirm changes to your Suitcase')
            ->setFrom('notice@winspireme.com')
            ->setTo($suitcase->getUser()->getEmail())
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:suitcase-unpacked.html.twig',
                    array('suitcase' => $suitcase)
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
        
        $this->mailer->getTransport()->start();
        return true;
    }
}