<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Comment;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class PartnerController extends Controller
{
    public function commentAction($id)
    {
        $request = $this->getRequest();
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        if($request->request->has('message') && $request->request->get('message') != '') {
            $message = $request->request->get('message');
        }
        else {
            return $response->setData(array(
                'success' => false
            ));
        }
        
        $user = $this->getUser();
        if(!$user) {
            return $response->setData(array(
                'success' => false
            ));
        }
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.sfPartnerId = :partnerId AND s.id = :id'
        )
            ->setParameter('partnerId', $user->getSfId())
            ->setParameter('id', $id)
        ;
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            return $response->setData(array(
                'success' => false
            ));
        }
        
        $name = $user->getFirstName() . ' ' . $user->getLastName();
        $email = $user->getEmail();
        
        $comment = new Comment();
        $comment->setName($name);
        $comment->setEmail($email);
        $comment->setMessage($message);
        $comment->setSuitcase($suitcase);
        
        $em->persist($comment);
        $em->flush();
        
        $msg = array('comment_id' => $comment->getId());
        $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'comment');
        
        
        $timestamp = date_format($comment->getCreated(), 'M j, Y, g:i a');
        
        return $response->setData(array(
            'success' => true,
            'name' => $name,
            'message' => nl2br($message),
            'timestamp' => $timestamp
        ));
    }
    
    public function listAction()
    {
        $user = $this->getUser();
        
        $qb = $this->getDoctrine()->getManager()->createQueryBuilder();
        $qb->select(array('s', 'i', 'p'));
        $qb->from('InertiaWinspireBundle:Suitcase', 's');
        $qb->leftJoin('s.items', 'i', 'WITH', 'i.status != \'X\'');
        $qb->leftJoin('i.package', 'p');
        $qb->andWhere('s.sfPartnerId = :partner_id');
        $qb->setParameter('partner_id', $user->getCompany()->getSfId());
        
        $suitcases = $qb->getQuery()->getResult();
        
        return $this->render('InertiaWinspireBundle:Partner:list.html.twig', array(
            'suitcases' => $suitcases,
        ));
    }
    
    public function shareAction($id)
    {
        $partner = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        
        $query = $em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.sfPartnerId = :partnerId AND s.id = :id'
        )
            ->setParameter('partnerId', $partner->getCompany()->getSfId())
            ->setParameter('id', $id)
        ;
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            throw $this->createNotFoundException();
        }
        
        $user = $suitcase->getUser();
        
        // TODO move to entity class method
        $downloadLinks = array();
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            $counts[$item->getStatus()]++;
            $downloadLinks[$item->getPackage()->getId()] = $this->getDownloadLink($item->getPackage()->getSfContentPackId());
        }
        
        return $this->render('InertiaWinspireBundle:Partner:share.html.twig', array(
            'user' => $user,
            'suitcase' => $suitcase,
            'counts' => $counts,
            'pages' => ceil(count($suitcase->getItems()) / 6),
            'downloadLinks' => $downloadLinks
        ));
    }
    
    
    // TODO refactor this method from here and SuitcaseController
    protected function getDownloadLink($id)
    {
        $em = $this->getDoctrine()->getManager();
        
        $query = $em->createQuery(
            'SELECT c, v FROM InertiaWinspireBundle:ContentPack c LEFT JOIN c.versions v WHERE c.sfId = :id ORDER BY v.updated DESC'
        )
            ->setParameter('id', $id)
            ->setMaxResults(1)
        ;
        
        try {
            $contentPack = $query->getSingleResult();
            $versions = $contentPack->getVersions();
            $version = $versions[0];
            $version = $version->getId();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $version = false;
        }
        
        return $version;
    }
}
