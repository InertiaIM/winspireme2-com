<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use Inertia\WinspireBundle\Form\Type\AccountType2;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SuitcaseController extends Controller
{
    public function addAction($id)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        
        $query = $em->createQuery(
            'SELECT i FROM InertiaWinspireBundle:SuitcaseItem i WHERE i.suitcase = :suitcase_id AND i.package = :package_id'
        )
        ->setParameter('suitcase_id', $suitcase->getId())
        ->setParameter('package_id', $id);
        
        try {
            // If found, we already have this item in our cart
            // and we can just return an empty data object
            $item = $query->getSingleResult();
            return $response->setData(array(
            ));
        }
        catch (\Doctrine\Orm\NoResultException $e) {
        }
        
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.id = :id'
        )
        ->setParameter('id', $id);
        
        try {
            $package = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            throw $this->createNotFoundException();
        }
        
        $suitcaseItem = new SuitcaseItem();
        $suitcaseItem->setPackage($package);
        $suitcaseItem->setQuantity(1);
        $suitcaseItem->setPrice(0);
        $suitcaseItem->setSubtotal(0);
        $suitcaseItem->setTotal(0);
        $suitcaseItem->setStatus('M');
        $em->persist($suitcaseItem);
        
        $suitcase->addItem($suitcaseItem);
        if($suitcase->getPacked()) {
            // reopen suitcase and trigger reminder message
            $suitcase->setPacked(false);
            $this->retrigger($suitcase);
        }
        $suitcase->setUpdated($suitcaseItem->getUpdated());
        
        $em->persist($suitcase);
        $em->flush();
        
        $response->setData(array(
            'count' => count($suitcase->getItems()),
            'item' => array(
                'id' => $package->getId(),
                'slug' => $package->getSlug(),
                'thumbnail' => $package->getThumbnail(),
                'parentHeader' => $package->getParentHeader(),
                'persons' => $package->getPersons(),
                'accommodations' => $package->getAccommodations(),
                'airfares' => $package->getAirfares()
            )
        ));
        
        return $response;
    }
    
    public function buttonWidgetAction()
    {
        $suitcase = $this->getSuitcase();
        
        return $this->render('InertiaWinspireBundle:Suitcase:buttonWidget.html.twig',
            array(
                'suitcase' => $suitcase
            )
        );
    }
    
    public function deleteAction($id)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        $items = $suitcase->getItems();
        $deleted = false;
        foreach($items as $item) {
            if($id == $item->getPackage()->getId()) {
                $em->remove($item);
                $suitcase->setUpdated(new \DateTime());
                
                if($suitcase->getPacked()) {
                    // reopen suitcase and trigger reminder message
                    $suitcase->setPacked(false);
                    $this->retrigger($suitcase);
                }
                
                $em->persist($suitcase);
                $em->flush();
                
                $deleted = true;
            } 
        }
        
        $response->setData(array(
            'deleted' => $deleted,
            'count' => count($suitcase->getItems()),
            'counts' => $this->getCounts($suitcase)
        ));
        
        return $response;
    }
    
    public function flagAction($id)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        $items = $suitcase->getItems();
        $newStatus = false;
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($items as $item) {
            $counts[$item->getStatus()]++;
            
            if($id == $item->getPackage()->getId()) {
                switch($item->getStatus()) {
                    case 'M':
                        $item->setStatus('D');
                        $newStatus = 'D';
                        $counts['M']--;
                        $counts['D']++;
                        break;
                    case 'D':
                        $item->setStatus('M');
                        $newStatus = 'M';
                        $counts['D']--;
                        $counts['M']++;
                        break;
                    case 'R':
                        $item->setStatus('E');
                        $newStatus = 'E';
                        $counts['R']--;
                        $counts['E']++;
                        break;
                    case 'E':
                        $item->setStatus('R');
                        $newStatus = 'R';
                        $counts['E']--;
                        $counts['R']++;
                        break;
                }
                
                $em->persist($item);
                $em->flush();
            }
        }
        
        $response->setData(array(
            'status' => $newStatus,
            'counts' => $counts
        ));
        
        return $response;
    }
    
    
    public function flagsAction(Request $request)
    {
        $response = new JsonResponse();
        $ids = $request->query->get('ids');
        
        if(count($ids) < 1) {
            $ids = array();
        }
        
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        $items = $suitcase->getItems();
        $newStatus = false;
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        
        foreach($ids as $element) {
            foreach($items as $item) {
                if($element['id'] == $item->getPackage()->getId()) {
                    $item->setStatus($element['status']);
                    $em->persist($item);
                    $em->flush();
                }
            }
        }
        
        foreach($items as $item) {
            $counts[$item->getStatus()]++;
        }
        
        
        $response->setData(array(
            'counts' => $counts
        ));
        
        return $response;
    }
    
    
    public function viewAction(Request $request)
    {
        $user = $this->getUser();
        $form = $this->createForm(new AccountType2(), $user->getCompany());
        $form->get('newsletter')->setData($user->getNewsletter());
        $form->get('phone')->setData($user->getPhone());
        
        $suitcase = $this->getSuitcase();
        if(!$suitcase) {
            throw $this->createNotFoundException();
        }
        
        $form->get('name')->setData($suitcase->getEventName());
        
        if($suitcase->getEventDate() != '') {
            $form->get('date')->setData($suitcase->getEventDate()->format('m/d/Y'));
        }
        
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            $counts[$item->getStatus()]++;
        }
        
        return $this->render('InertiaWinspireBundle:Suitcase:guest.html.twig', array(
            'creator' => $suitcase->getUser(),
            'form' => $form->createView(),
            'suitcase' => $suitcase,
            'counts' => $counts,
            'pages' => ceil(count($suitcase->getItems()) / 6)
        ));
    }
    
    public function packAction(Request $request)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        if(!$suitcase) {
            throw $this->createNotFoundException();
        }
        
        $user = $this->getUser();
        $account = $user->getCompany();
        $form = $this->createForm(new AccountType2(), $account);
        
        // process the form on POST
        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $first = true;
                if($suitcase->getPackedAt() != '') {
                    $first = false;
                }
                
                $eventDate = new \DateTime($form->get('date')->getData());
                $suitcase->setEventName($form->get('name')->getData());
                $suitcase->setEventDate($eventDate);
                $suitcase->setPacked(true);
                $suitcase->setPackedAt(new \DateTime());
                
                $em->persist($suitcase);
                $em->persist($account);
                $em->flush();
                
                $msg = array('suitcase_id' => $suitcase->getId(), 'first' => $first);
                $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'pack-suitcase');
                
                return $response->setData(array(
                    'packed' => true
                ));
            }
            else {
                // TODO there has to be a better way to iterate through the
                // possible errors.
                $address = $form->get('address');
                $address2 = $form->get('address2');
                $city = $form->get('city');
                $state = $form->get('state');
                $zip = $form->get('zip');
                $phone = $form->get('phone');
                $name = $form->get('name');
                $date = $form->get('date');
                $loa = $form->get('loa');
                $terms = $form->get('terms');
                
                $errors = array();
                
                if($blahs = $address->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_address'] = $temp;
                }
                
                if($blahs = $address2->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_address2'] = $temp;
                }
                
                if($blahs = $city->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_city'] = $temp;
                }
                
                if($blahs = $state->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_state'] = $temp;
                }
                
                if($blahs = $zip->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_zip'] = $temp;
                }
                
                if($blahs = $phone->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_phone'] = $temp;
                }
                
                if($blahs = $name->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_name'] = $temp;
                }
                
                if($blahs = $date->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_date'] = $temp;
                }
                
                if($blahs = $loa->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_loa'] = $temp;
                }
                
                if($blahs = $terms->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['account_terms'] = $temp;
                }
                
                return $response->setData(array(
                    'errors' => $errors
                ));
            }
        }
        
        return $this->render('InertiaWinspireBundle:Account:create.html.twig', array(
            'form' => $form->createView()
        ));
    }
    
    public function previewAction()
    {
        $suitcase = $this->getSuitcase();
        
        return $this->render('InertiaWinspireBundle:Suitcase:preview.html.twig', array(
            'suitcase' => $suitcase
        ));
    }
    
    protected function getCounts($suitcase) {
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            $counts[$item->getStatus()]++;
        }
        
        return $counts;
    }
    
    protected function getSuitcase() {
        // Establish which suitcase to use for current user
        $user = $this->getUser();
        
        if(!$user) {
            return false;
        }
        
        $session = $this->getRequest()->getSession();
        $em = $this->getDoctrine()->getManager();
        
        // First, check the current session for a suitcase id
        $sid = $session->get('sid');
        if($sid) {
//echo 'Found SID, step 1: ' . $sid . "<br/>\n";
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.user = :user_id AND s.id = :id ORDER BY i.updated DESC'
            )
            ->setParameter('user_id', $user->getId())
            ->setParameter('id', $sid);
            
            try {
                $suitcase = $query->getSingleResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                
                // If the suitcase we were expecting doesn't exist, we'll create a new one
//                throw $this->createNotFoundException();
                $suitcase = new Suitcase();
                $suitcase->setUser($user);
                $suitcase->setPacked(false);
                $em->persist($suitcase);
                $em->flush();
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
            
            return $suitcase;
        }
        // Second, query for the most recent suitcase (used as default)
        else {
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.user = :user_id ORDER BY s.updated DESC, i.updated DESC'
            )->setParameter('user_id', $user->getId());
            
            try {
                $suitcase = $query->getResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                throw $this->createNotFoundException();
            }
            
            if(count($suitcase) > 0) {
                $suitcase = $suitcase[0];
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
            else {
                // Third, no existing suitcases found for this account... create a new one
                $suitcase = new Suitcase();
                $suitcase->setUser($user);
                $suitcase->setPacked(false);
                
                $em->persist($suitcase);
                $em->flush();
                
                $session->set('sid', $suitcase->getId());
                
                return $suitcase;
            }
        }
    }
    
    protected function retrigger($s) {
        $msg = array('suitcase_id' => $s->getId());
        $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'unpack-suitcase');
    }
}
