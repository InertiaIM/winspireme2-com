<?php
namespace Inertia\WinspireBundle\Controller;

use Inertia\WinspireBundle\Entity\Share;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use Inertia\WinspireBundle\Form\Type\AccountType2;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\True;

class SuitcaseController extends Controller
{
    public function addAction($id)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        
        $suitcase = $this->getSuitcase();
        
        $query = $em->createQuery(
            'SELECT i FROM InertiaWinspireBundle:SuitcaseItem i WHERE i.suitcase = :suitcase_id AND i.package = :package_id AND i.status != \'X\''
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
        
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.picture IS NOT NULL AND p.id = :id'
            )
                ->setParameter('id', $id)
            ;
        }
        else {
            $query = $em->createQuery(
                'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.id = :id'
            )
                ->setParameter('id', $id)
            ;
        }
        
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
        if ($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $suitcaseItem->setStatus('R');
        }
        else {
            $suitcaseItem->setStatus('M');
        }
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
    
    public function adminAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $session = $this->getRequest()->getSession();
        
        if ($id == 'none') {
            $query = $em->createQuery(
                'SELECT s, u, c FROM InertiaWinspireBundle:Suitcase s JOIN s.user u JOIN u.company c ORDER BY c.name ASC'
            );
            $suitcases = $query->getResult();
            
            $query = $em->createQuery(
                'SELECT u FROM InertiaWinspireBundle:User u WHERE u.type = :type'
            )
                ->setParameter('type', 'S')
            ;
            $consultants = $query->getResult();
            
            return $this->render('InertiaWinspireBundle:Suitcase:admin.html.twig',
                array(
                    'consultants' => $consultants,
                    'suitcases' => $suitcases
                )
            );
        }
        else {
            $session->set('sid', $id);
            
            return $this->redirect($this->generateUrl('suitcaseView'));
        }
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
    
    public function createAction(Request $request)
    {
        $json = false;
        if ($request->query->get('format') == 'json') {
            $json = true;
            $response = new JsonResponse();
        }
        
        
        $session = $this->getRequest()->getSession();
        $em = $this->getDoctrine()->getManager();
        $formFactory = $this->get('form.factory');
        
        $form = $formFactory->createNamed('suitcase', 'form', null, array('csrf_protection' => false));
        $form->add(
            $formFactory->createNamed('name', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => 'Event Name',
                    'mapped' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('date', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => 'Event Date',
                    'mapped' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('package', 'hidden', null,
                array(
                    'mapped' => false,
                    'required' => false
                )
            )
        );
        
        
        
        // process the form on POST
        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                    $sid = $session->get('sid');
                    
                    if ($sid) {
                        $query = $em->createQuery(
                            'SELECT s, u FROM InertiaWinspireBundle:Suitcase s JOIN s.user u WHERE s.id = :sid'
                        )
                            ->setParameter('sid', $sid)
                        ;
                        
                        $user = $query->getSingleResult()->getUser();
                    }
                    else {
                        return $this->redirect($this->generateUrl('suitcaseAdmin'));
                    }
                }
                else {
                    $user = $this->getUser();
                }
                
                $suitcase = new Suitcase();
                $suitcase->setPacked(false);
                $suitcase->setDirty(true);
                $suitcase->setName($form->get('name')->getData());
                $suitcase->setEventDate(new \DateTime($form->get('date')->getData()));
                $suitcase->setUser($user);
                
                if($form->get('package')->getData() != '') {
                    // TODO Can I call the SuitcaseController::addAction directly
                    // rather than repeating the logic here?
                    
                    $id = $form->get('package')->getData();
                    $query = $em->createQuery(
                        'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.is_private != 1 AND p.picture IS NOT NULL AND p.id = :id'
                    )
                    ->setParameter('id', $id);
                    
                    try {
                        $package = $query->getSingleResult();
                        
                        $suitcaseItem = new SuitcaseItem();
                        $suitcaseItem->setPackage($package);
                        $suitcaseItem->setQuantity(1);
                        $suitcaseItem->setPrice(0);
                        $suitcaseItem->setSubtotal(0);
                        $suitcaseItem->setTotal(0);
                        $suitcaseItem->setStatus('M');
                        
                        $suitcase->addItem($suitcaseItem);
                        $em->persist($suitcaseItem);
                        
                        if ($json) {
                            $items = array(array(
                                'id' => $package->getId(),
                                'slug' => $package->getSlug(),
                                'thumbnail' => $package->getThumbnail(),
                                'parentHeader' => $package->getParentHeader(),
                                'persons' => $package->getPersons(),
                                'accommodations' => $package->getAccommodations(),
                                'airfares' => $package->getAirfares()
                            ));
                            $count = 1;
                        }
                    }
                    catch (\Doctrine\Orm\NoResultException $e) {
                    }
                }
                else {
                    // No packages added to Suitcase (new Suitcase directly)
                    if ($json) {
                        $items = array();
                        $count = 0;
                    }
                }
                
                $em->persist($suitcase);
                $em->flush();
                
                try {
                    $msg = array('suitcase_id' => $suitcase->getId());
                    $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'create-suitcase');
                }
                catch (\Exception $e) {
                    $this->get('logger')->err('Rabbit queue (create-suitcase) es no bueno!');
                }
                
                $session->set('sid', $suitcase->getId());
                
                
                if ($json) {
                    return $response->setData(array(
                        'count' => $count,
                        'items' => $items,
                        'locked' => $suitcase->getPacked(),
                        'suitcase' => array(
                            'id' => $suitcase->getId(),
                            'name' => $suitcase->getName()
                        )
                    ));
                }
                else {
                    return $this->redirect($this->generateUrl('suitcaseView'));
                }
            }
            else {
                // TODO there has to be a better way to iterate through the
                // possible errors.
                $name = $form->get('name');
                $date = $form->get('date');
                
                $errors = array();
                
                if($blahs = $name->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['name'] = $temp;
                }
                
                if($blahs = $date->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['date'] = $temp;
                }
                
                
                return $response->setData(array(
                    'errors' => $errors
                ));
            }
        }
        
        return $this->render('InertiaWinspireBundle:Suitcase:create.html.twig', array(
            'form' => $form->createView()
        ));
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
                $item->setStatus('X');
                $suitcase->setUpdated(new \DateTime());
                
                if($suitcase->getPacked()) {
                    // reopen suitcase and trigger reminder message
                    $suitcase->setPacked(false);
                    $this->retrigger($suitcase);
                }
                
                $em->persist($item);
                $em->persist($suitcase);
                $em->flush();
                
                $deleted = true;
            } 
        }
        
        $response->setData(array(
            'deleted' => $deleted,
            'count' => $deleted ? count($suitcase->getItems()) - 1 : count($suitcase->getItems()),
            'counts' => $this->getCounts($suitcase)
        ));
        
        return $response;
    }
    
    
    public function downloadAction($suitcaseId)
    {
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.id = :id'
            )
                ->setParameter('id', $suitcaseId)
            ;
        }
        else {
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.id = :id AND s.user = :uid'
            )
                ->setParameter('id', $suitcaseId)
                ->setParameter('uid', $user->getId())
            ;
        }
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Exception $e) {
            throw $this->createNotFoundException();
        }
        
        
        $sfContentPackIds = array();
        $sfContentPacks = array();
        foreach($suitcase->getItems() as $item) {
            if ($item->getPackage()->getSfContentPackId() != '' || $item->getPackage()->getIsDefault()) {
                $contentPackId = $item->getPackage()->getSfContentPackId();
                $sfContentPackIds[] = $contentPackId;
            }
            else {
                // If the Package variant doesn't have its own SF Content Pack, then we'll try to find
                // the content pack associated with the default Package with the same parent header.
                // TODO this really should be pushed into the model... too much logic in our controller.
                $query = $em->createQuery(
                    'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.parent_header = :ph AND p.is_default = 1 ORDER BY p.active ASC, p.created DESC'
                )
                    ->setParameter('ph', $item->getPackage()->getParentHeader())
                    ->setMaxResults(1)
                ;
                
                try {
                    $p = $query->getSingleResult();
                    $contentPackId = $p->getSfContentPackId();
                }
                catch (\Exception $e) {
                    $contentPackId = '';
                }
                
                $sfContentPackIds[] = $contentPackId;
            }
            
            $sfContentPacks[$contentPackId] = $item->getPackage()->getSlug();
        }
        
        
        $edate = $suitcase->getEventDate() ? $suitcase->getEventDate() : new \DateTime();
        $query = $em->createQuery(
            'SELECT c, v FROM InertiaWinspireBundle:ContentPack c LEFT JOIN c.versions v WHERE c.sfId IN (:ids) AND v.updated <= :edate ORDER BY v.updated ASC'
        )
            ->setParameter('ids', $sfContentPackIds)
            ->setParameter('edate', $edate)
        ;
        
        $contentPacks = $query->getResult();
        
        $versions = array();
        foreach($contentPacks as $cp) {
            foreach($cp->getVersions() as $version) {
                $versions[$cp->getSfId()] = $version;
            }
        }
        
        
        
        $zip = new \ZipArchive();
        $filename = tempnam(null, null);
        
        if ($zip->open($filename, \ZipArchive::OVERWRITE) !== true) {
            exit("cannot open tmp file\n");
        }
        
        foreach($versions as $key => $version) {
            $zip->addEmptyDir($sfContentPacks[$key]);
            foreach($version->getFiles() as $file) {
                $zip->addFile(
                    $this->container->getParameter('kernel.root_dir') . '/documents/' . $version->getSfId() . '/' . $file->getName(),
                    $sfContentPacks[$key] . '/' . $file->getName()
                );
            }
        }
        $zip->close();
        
        $response = new Response();
        $response->headers->set('Content-Disposition', 'attachment; filename="' . 'Winspire-Materials.zip' . '"');
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Description', 'File Transfer');
        $response->headers->set('Content-Length', filesize($filename));
        $response->headers->set('Content-Transfer-Encoding', 'binary');
        
        return $response->setContent(file_get_contents($filename));
    }
    
    
    public function editAction(Request $request)
    {
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        
        if ($request->isMethod('POST')) {
            $suitcase = $request->request->get('suitcase');
            $id = $suitcase['id'];
            $name = $suitcase['name'];
            $eventName = $suitcase['event_name'];
            $eventDate = $suitcase['event_date'];
            
            $query = $em->createQuery(
                'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.user = :user_id AND s.id = :id'
            )
                ->setParameter('user_id', $user->getId())
                ->setParameter('id', $id)
            ;
            
            try {
                $suitcase = $query->getSingleResult();
                $suitcase->setName($name);
                $suitcase->setEventName($eventName);
                if($eventDate != '') {
                    $suitcase->setEventDate(new \DateTime($eventDate));
                }
                else {
                    $suitcase->setEventDate(null);
                }
                $suitcase->setDirty(true);
                
                $em->persist($suitcase);
                $em->flush();
                
                return $response->setData(array(
                    'success' => true,
                    'suitcase' => array(
                        'id' => $suitcase->getId(),
                        'name' => $suitcase->getName(),
                        'event_name' => $suitcase->getEventName(),
                        'event_date' => $suitcase->getEventDate() != '' ? $suitcase->getEventDate()->format('m/d/y') : ''
                    )
                ));
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                return $response->setData(false);
            }
        }
        
        return $response->setData(false);
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
    
    
    public function killAction($id)
    {
        // TODO What to do with orphaned Opportunity in SF?
        $response = new JsonResponse();
        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.user = :user_id AND s.id = :id'
        )
            ->setParameter('user_id', $user->getId())
            ->setParameter('id', $id)
        ;
        
        try {
            $suitcase = $query->getSingleResult();
            $em->remove($suitcase);
            $em->flush();
            
            return $response->setData(array(
                'success' => true,
                'suitcase' => array(
                    'id' => $id
                )
            ));
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            return $response->setData(false);
        }
        
        return $response->setData(false);
    }
    
    
    public function viewAction(Request $request)
    {
        $suitcase = $this->getSuitcase('alpha');
        
        if(!$suitcase) {
            if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                return $this->redirect($this->generateUrl('suitcaseAdmin'));
            }
            throw $this->createNotFoundException();
        }
        
        
        
        $user = $suitcase->getUser();
        
        // Get full list of Suitcases owned by this user
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.user = :user ORDER BY s.name ASC'
        )
            ->setParameter('user', $user)
        ;
        $suitcases = $query->getResult();
        
        $suitcaseList = array();
        foreach($suitcases as $s) {
            $suitcaseList[] = array('id' => $s->getId(), 'name' => $s->getName());
        }
        
        $form = $this->createForm(new AccountType2(), $user->getCompany());
        $form->get('phone')->setData($user->getPhone());
        
        
        
        $share = $this->shareAction();
        $share->get('suitcase')->setData($suitcase->getId());
        
        $form->get('name')->setData($suitcase->getEventName());
        
        if($suitcase->getEventDate() != '') {
            $form->get('date')->setData($suitcase->getEventDate()->format('m/d/Y'));
        }
        
        $downloadLinks = array();
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            $counts[$item->getStatus()]++;
            
            if ($item->getPackage()->getSfContentPackId() != '' || $item->getPackage()->getIsDefault()) {
                $downloadLinks[$item->getPackage()->getId()] = $this->getDownloadLink($item->getPackage()->getSfContentPackId());
            }
            else {
                // If the Package variant doesn't have its own SF Content Pack, then we'll try to find
                // the content pack associated with the default Package with the same parent header.
                // TODO this really should be pushed into the model... too much logic in our controller.
                
                $em = $this->getDoctrine()->getManager();
                $query = $em->createQuery(
                    'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.parent_header = :ph AND p.is_default = 1 ORDER BY p.active ASC, p.created DESC'
                )
                    ->setParameter('ph', $item->getPackage()->getParentHeader())
                    ->setMaxResults(1)
                ;
                
                try {
                    $p = $query->getSingleResult();
                    $contentPackId = $p->getSfContentPackId();
                }
                catch (\Exception $e) {
                    $contentPackId = '';
                }
                
                $downloadLinks[$item->getPackage()->getId()] = $this->getDownloadLink($contentPackId);
            }
        }
        
        return $this->render('InertiaWinspireBundle:Suitcase:view.html.twig', array(
            'creator' => $suitcase->getUser(),
            'form' => $form->createView(),
            'share' => $share->createView(),
            'suitcase' => $suitcase,
            'counts' => $counts,
            'pages' => ceil(count($suitcase->getItems()) / 6),
            'downloadLinks' => $downloadLinks,
            'suitcaseList' => $suitcaseList
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
        
        $user = $suitcase->getUser();
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
        $user = $suitcase->getUser();
        
        // Get full list of Suitcases owned by this user
        $em = $this->getDoctrine()->getManager();
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.user = :user ORDER BY s.name ASC'
        )
        ->setParameter('user', $user)
        ;
        $suitcases = $query->getResult();
        
        $suitcaseList = array();
        foreach($suitcases as $s) {
            $suitcaseList[] = array('id' => $s->getId(), 'name' => $s->getName());
        }
        
        return $this->render('InertiaWinspireBundle:Suitcase:preview.html.twig', array(
            'suitcase' => $suitcase,
            'suitcaseList' => $suitcaseList
        ));
    }
    
    public function shareAction()
    {
        $request = $this->getRequest();
        $response = new JsonResponse();
        
        // TODO refactor into form type?
        $form = $this->get('form.factory')->createNamedBuilder('share', 'form', array());
        
        $form->add('name', 'form');
        $form->add('email', 'form');
        $form->add('message', 'textarea');
        $form->add('suitcase', 'hidden');
        
        $form->get('message')->setData('I’ve invited you to my Winspire Suitcase. I am using Winspire to select some amazing auction items for our upcoming fundraising auction. Please review at your earliest convenience and let me know what you think about my choices!');
        
        $form->get('name')->add('1', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Name',
                'required' => false
            )
        );
        $form->get('email')->add('1', 'text',
            array(
                'constraints' => array(
                    new Email(),
                ),
                'label' => 'Email',
                'required' => false
            )
        );
        $form->get('name')->add('2', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Name',
                'required' => false
            )
        );
        $form->get('email')->add('2', 'text',
            array(
                'constraints' => array(
                    new Email(),
                ),
                'label' => 'Email',
                'required' => false
            )
        );
        $form->get('name')->add('3', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Name',
                'required' => false
            )
        );
        $form->get('email')->add('3', 'text',
            array(
                'constraints' => array(
                    new Email(),
                ),
                'label' => 'Email',
                'required' => false
            )
        );
        $form->get('name')->add('4', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Name',
                'required' => false
            )
        );
        $form->get('email')->add('4', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Email',
                'required' => false
            )
        );
        $form->get('name')->add('5', 'text',
            array(
                'constraints' => array(
                ),
                'label' => 'Name',
                'required' => false
            )
        );
        $form->get('email')->add('5', 'text',
            array(
                'constraints' => array(
                    new Email(),
                ),
                'label' => 'Email',
                'required' => false
            )
        );
        
        $form = $form->getForm();
        
        
        // process the form on POST
        if ($request->isMethod('POST')) {
            $form->bind($request);
            $errors = array();
            if ($form->isValid()) {
                $user = $this->getUser();
                $em = $this->getDoctrine()->getManager();
                
                
                if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                    $query = $em->createQuery(
                        'SELECT s, h FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.shares h WHERE s.id = :id'
                    )
                        ->setParameter('id', $form->get('suitcase')->getData())
                    ;
                }
                else {
                    $query = $em->createQuery(
                        'SELECT s, h FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.shares h WHERE s.user = :user_id AND s.id = :id'
                    )
                        ->setParameter('user_id', $user->getId())
                        ->setParameter('id', $form->get('suitcase')->getData())
                    ;
                }
                
                try {
                    $suitcase = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    return $response->setData(array(
                        'formerror' => 'no suitcase'
                    ));
                }
                
                $shares = $suitcase->getShares();
                $names = $form->get('name');
                $emails = $form->get('email');
                $successes = array();
                foreach($names as $key => $formItem) {
                    if($formItem->getData() != '') {
                        $name = $formItem->getData();
                        $email = $emails[$key]->getData();
                        
                        $exists = false;
                        foreach($shares as $share) {
                            if($email === $share->getEmail()) {
                                $exists = true;
                                $id = $share->getId();
                            }
                        }
                        
                        if(!$exists) {
                            $share = new Share();
                            $share->setName($name);
                            $share->setEmail($email);
                            $share->setActive(true);
                            $share->setToken($this->generateToken());
                            $share->setMessage($form->get('message')->getData());
                            
                            $suitcase->addShare($share);
                            
                            $em->persist($share);
                            $em->flush();
                            
                            $msg = array('share_id' => $share->getId());
                            $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'share-suitcase');
                            
                            $successes[] = array('name' => $name, 'email' => $email);
                        }
                        else {
                            $errors[] = array('name' => $name, 'email' => $email, 'id' => $id);
                        }
                    }
                }
                
                $response->setData(array(
                    'successes' => $successes,
                    'errors' => $errors
                ));
                
                return $response;
            }
            else {
                return $response->setData(array(
                    'formerror' => 'invalid'
                ));
            }
        }
        else {
            return $form;
        }
    }
    
    
    public function shareAgainAction($id)
    {
        $response = new JsonResponse();
        
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT s, h FROM InertiaWinspireBundle:Share h JOIN h.suitcase s WHERE h.id = :id'
            )
                ->setParameter('id', $id)
            ;
        }
        else {
            $query = $em->createQuery(
                'SELECT s, h FROM InertiaWinspireBundle:Share h JOIN h.suitcase s WHERE s.user = :user_id AND h.id = :id'
            )
                ->setParameter('user_id', $user->getId())
                ->setParameter('id', $id)
            ;
        }
        
        try {
            $share = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            return $response->setData(array(
                'success' => false
            ));
        }
        
        $msg = array('share_id' => $share->getId());
        $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'share-suitcase');
        
        return $response->setData(array(
            'success' => true
        ));
    }
    
    
    public function switchAction(Request $request)
    {
        $json = false;
        if ($request->query->get('format') == 'json') {
            $json = true;
            $response = new JsonResponse();
        }
        
        
        $em = $this->getDoctrine()->getManager();
        $session = $this->getRequest()->getSession();
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            $query = $em->createQuery(
                'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' LEFT JOIN i.package p WHERE s.id = :sid'
            )
                ->setParameter('sid', $request->query->get('sid'))
            ;
            
        }
        else {
            $user = $this->getUser();
            $query = $em->createQuery(
                'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' LEFT JOIN i.package p WHERE s.id = :sid AND s.user = :user'
            )
                ->setParameter('sid', $request->query->get('sid'))
                ->setParameter('user', $user)
            ;
        }
        
        try {
            $suitcase = $query->getSingleResult();
            $session->set('sid', $suitcase->getId());
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // Do nothing, leave the session alone
            // We didn't find an appropriate suitcase
        }
        
        
        if ($json) {
            $items = array();
            foreach ($suitcase->getItems() as $item) {
                $package = $item->getPackage();
                $items[] = array(
                    'id' => $package->getId(),
                    'slug' => $package->getSlug(),
                    'thumbnail' => $package->getThumbnail(),
                    'parentHeader' => $package->getParentHeader(),
                    'persons' => $package->getPersons(),
                    'accommodations' => $package->getAccommodations(),
                    'airfares' => $package->getAirfares()
                );
            }
            
            
            return $response->setData(array(
                'count' => count($suitcase->getItems()),
                'items' => $items,
                'locked' => $suitcase->getPacked(),
                'suitcase' => array(
                    'id' => $suitcase->getId(),
                    'name' => $suitcase->getName()
                )
            ));
        }
        else {
            return $this->redirect($this->generateUrl('suitcaseView'));
        }
    }
    
    
    protected function crypto_rand_secure($min, $max)
    {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }
    
    protected function generateToken()
    {
        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $codeAlphabet.= 'abcdefghijklmnopqrstuvwxyz';
        $codeAlphabet.= '0123456789';
        
        for($i = 0; $i < 20; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, strlen($codeAlphabet))];
        }
        return $token;
    }
    
    protected function getCounts($suitcase)
    {
        $counts = array('M' => 0, 'D' => 0, 'R' => 0, 'E' => 0);
        foreach($suitcase->getItems() as $item) {
            if ($item->getStatus() != 'X') {
                $counts[$item->getStatus()]++;
            }
        }
        
        return $counts;
    }
    
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
    
    protected function getSuitcase($order = 'update')
    {
        $em = $this->getDoctrine()->getManager();
        
        $session = $this->getRequest()->getSession();
        $sid = $session->get('sid');
        
        if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
            if($sid) {
                
                if($order == 'update') {
                    $query = $em->createQuery(
                        'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' LEFT JOIN i.package p WHERE s.id = :id ORDER BY i.updated DESC'
                    )
                        ->setParameter('id', $sid)
                    ;
                }
                else {
                    $query = $em->createQuery(
                        'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' LEFT JOIN i.package p WHERE s.id = :id ORDER BY p.parent_header ASC'
                    )
                        ->setParameter('id', $sid)
                    ;
                }
                
                try {
                    $suitcase = $query->getSingleResult();
                }
                catch (\Doctrine\Orm\NoResultException $e) {
                    throw $this->createNotFoundException();
//                    $suitcase = new Suitcase();
                }
                
                return $suitcase;
            }
            else {
                return false;
            }
        }
        
        
        
        
        
        // Establish which suitcase to use for current user
        $user = $this->getUser();
        
        if(!$user) {
            return false;
        }
        
        
        
        
        // First, check the current session for a suitcase id
//        $sid = $session->get('sid');
        if($sid) {
//echo 'Found SID, step 1: ' . $sid . "<br/>\n";
            if($order == 'update') {
                $query = $em->createQuery(
                    'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.user = :user_id AND s.id = :id ORDER BY i.updated DESC'
                )
                ->setParameter('user_id', $user->getId())
                ->setParameter('id', $sid);
            }
            else {
                $query = $em->createQuery(
                    'SELECT s, i, p FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' LEFT JOIN i.package p WHERE s.user = :user_id AND s.id = :id ORDER BY p.parent_header ASC'
                )
                ->setParameter('user_id', $user->getId())
                ->setParameter('id', $sid);
            }
            
            try {
                 $suitcase = $query->getSingleResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                
                // If the suitcase we were expecting doesn't exist, we'll create a new one
//                throw $this->createNotFoundException();
                $suitcase = new Suitcase();
//                $suitcase->setUser($user);
//                $suitcase->setPacked(false);
//                $em->persist($suitcase);
//                $em->flush();
//                
//                $session->set('sid', $suitcase->getId());
//                
//                return $suitcase;
            }
            
            return $suitcase;
        }
        // Second, query for the most recent suitcase (used as default)
        else {
            $query = $em->createQuery(
                'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.user = :user_id ORDER BY s.updated DESC, i.updated DESC'
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
    
    protected function retrigger($s)
    {
        $msg = array('suitcase_id' => $s->getId());
        $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'unpack-suitcase');
    }
}
