<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Comment;
use Inertia\WinspireBundle\Entity\Share;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use Inertia\WinspireBundle\Form\Type\AccountType2;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Email;

class ShareController extends Controller
{
    public function commentAction($id, $token)
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
        
        $send = false;
        
        if($token != 'none') {
            $query = $em->createQuery(
                'SELECT s, h FROM InertiaWinspireBundle:Share h JOIN h.suitcase s WHERE h.token = :token AND s.id = :id AND s.status IN (\'U\',\'P\')'
            )
                ->setParameter('token', $token)
                ->setParameter('id', $id)
            ;
            try {
                $share = $query->getSingleResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                return $response->setData(array(
                    'success' => false
                ));
            }
            
            $name = $share->getName();
            $email = $share->getEmail();
            $suitcase = $share->getSuitcase();
            
            $send = true;
        }
        else {
            $user = $this->getUser();
            
            if(!$user) {
                return $response->setData(array(
                    'success' => false
                ));
            }
            
            
            
            if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                $query = $em->createQuery(
                    'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.id = :id'
                )
                    ->setParameter('id', $id)
                ;
            }
            else {
                $query = $em->createQuery(
                    'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.user = :user_id AND s.id = :id'
                )
                    ->setParameter('user_id', $user->getId())
                    ->setParameter('id', $id)
                ;
            }
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
        }
        
        $comment = new Comment();
        $comment->setName($name);
        $comment->setEmail($email);
        $comment->setMessage($message);
        $comment->setSuitcase($suitcase);
        
        $em->persist($comment);
        $em->flush();
        
        if($send) {
            $msg = array('comment_id' => $comment->getId());
            $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'comment');
        }
        
        
        $timestamp = date_format($comment->getCreated(), 'M j, Y, g:i a');
        
        return $response->setData(array(
            'success' => true,
            'name' => $name,
            'message' => nl2br($message),
            'timestamp' => $timestamp
        ));
    }
    
    public function viewAction($token)
    {
        $em = $this->getDoctrine()->getManager();
        
        $query = $em->createQuery(
            'SELECT s, h, i FROM InertiaWinspireBundle:Share h JOIN h.suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE h.token = :token AND s.status IN (\'U\',\'P\')'
        )
            ->setParameter('token', $token)
        ;
        
        try {
            $share = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            throw $this->createNotFoundException();
        }
        
        $suitcase = $share->getSuitcase();
        $user = $suitcase->getUser(); 
        
        // TODO move to entity class method
        $downloadLinks = array();
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            $counts[$item->getStatus()]++;
            $downloadLinks[$item->getPackage()->getId()] = $this->getDownloadLink($item->getPackage()->getSfContentPackId());
        }
        
        return $this->render('InertiaWinspireBundle:Share:view.html.twig', array(
            'user' => $user,
            'suitcase' => $suitcase,
            'counts' => $counts,
            'pages' => ceil(count($suitcase->getItems()) / 6),
            'token' => $token,
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
