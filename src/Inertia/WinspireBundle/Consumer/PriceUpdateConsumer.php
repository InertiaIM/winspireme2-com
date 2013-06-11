<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class PriceUpdateConsumer implements ConsumerInterface
{
    protected $em;
    protected $sf;
    
    public function __construct(EntityManager $entityManager, Client $salesforce)
    {
        $this->em = $entityManager;
        $this->sf = $salesforce;
        
        $this->em->getConnection()->close();
    }
    
    public function execute(AMQPMessage $msg)
    {
        $this->em->getConnection()->connect();
        
        $body = unserialize($msg->body);
        $itemId = $body['item_id'];
        
        $query = $this->em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:SuitcaseItem i JOIN i.suitcase s WHERE i.id = :id'
        )->setParameter('id', $itemId);
        
        try {
            $suitcaseItem = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // If we can't get the Suitcase record we'll 
            // throw out the message from the queue (ack)
            return true;
        }
        
        // Make sure we have our user setup as an OpportunityContactRole
        if ($suitcaseItem->getSfId() != '') {
            $sfItem = new \stdClass();
            $sfItem->Id = $suitcaseItem->getSfId();
            $sfItem->Price_Paid_by_End_User__c = $suitcaseItem->getPrice();
            
            $saveResult = $this->sf->update(array($sfItem), 'OpportunityLineItem');
            
            if($saveResult[0]->success) {
            }
        }
        
        
        $this->em->clear();
        $this->em->getConnection()->close();
        
        return true;
    }
}