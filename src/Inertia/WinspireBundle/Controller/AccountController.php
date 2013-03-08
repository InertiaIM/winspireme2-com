<?php
namespace Inertia\WinspireBundle\Controller;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Security\LoginManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
//use Inertia\WinspireBundle\Entity\User;
use Inertia\WinspireBundle\Form\Type\AccountType;
use Inertia\WinspireBundle\Form\Type\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\True;

class AccountController extends Controller
{
    public function createAction(Request $request)
    {
        $response = new JsonResponse();
        $session = $this->getRequest()->getSession();
        $em = $this->getDoctrine()->getManager();
        $repository = $this->getDoctrine()->getRepository('InertiaWinspireBundle:User');
        $salesperson = $repository->findOneById(1);
        $userManager = $this->get('fos_user.user_manager');
        $loginManager = $this->get('fos_user.security.login_manager');
        $formFactory = $this->get('form.factory');
        
        $account = new Account();
        $user = $userManager->createUser();
        $account->addUser($user);
        
        $form = $this->container->get('fos_user.registration.form.factory')->createForm();
        $form->setData($user);
//        $form->remove('username');
        
        $accountForm = $this->createForm(new AccountType(), $account);
        $form->add($accountForm);
        
        $form->add(
            $formFactory->createNamed('firstName', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => 'First Name'
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('lastName', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => 'Last Name'
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('phone', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank(),
                    )
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('newsletter', 'checkbox', null,
                array(
                    'label' => 'Please sign me up for the Winspire newsletter, offers and important updates.',
                    'required' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('suitcase', 'text', null,
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new Length(array('min' => 3))
                    ),
                    'label' => 'Name Your Suitcase',
                    'mapped' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('referred', 'text', null,
                array(
                    'constraints' => array(
                    ),
                    'label' => 'Were you referred by anyone?',
                    'mapped' => false,
                    'required' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('terms', 'checkbox', null,
                array(
                    'constraints' => array(
                        new True(array(
                            'message' => 'You must agree to the Terms of Use before proceeding.'
                        )),
                    ),
                    'mapped' => false,
                    'required' => true
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
            // TODO has to be a better technique for working with form elements and validation/constraints
            // This trick is absolutely stupid...
            $original = $request->request->all();
            $original['fos_user_registration_form']['username'] = $original['fos_user_registration_form']['email'];
            $request->request->add($original);
            
            $form->bind($request);
            if ($form->isValid()) {
                // TODO create logic to check for existing Accounts before
                // allowing a new account creation.
                $user->setType('C');
                $user->setEnabled(true);
                $userManager->updateUser($user);
                
                // TODO configure cascade persist to avoid the extra calls to the EM
                $account->setSalesperson($salesperson);
                $em->persist($account);
                $em->flush();
                
                $user->setCompany($account);
                $user->setPlainPassword($user->getPassword());
                $userManager->updateUser($user);
                
                $suitcase = new Suitcase();
                $suitcase->setPacked(false);
                $suitcase->setName($form->get('suitcase')->getData());
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
                    }
                    catch (\Doctrine\Orm\NoResultException $e) {
                    }
                }
                
                $em->persist($suitcase);
                $em->flush();
                
                $msg = array('suitcase_id' => $suitcase->getId());
                $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'create-suitcase');
                
                $loginManager->loginUser('main', $user);
                
                $session->set('sid', $suitcase->getId());
                
                
                return $response;
            }
            else {
                // TODO there has to be a better way to iterate through the
                // possible errors.
                $terms = $form->get('terms');
                $name = $form->get('account')->get('name');
                $state = $form->get('account')->get('state');
                $zip = $form->get('account')->get('zip');
                $suitcase = $form->get('suitcase');
                $firstName = $form->get('firstName');
                $lastName = $form->get('lastName');
                $phone = $form->get('phone');
                $password = $form->get('plainPassword')->get('first');
                $confirm = $form->get('plainPassword')->get('second');
                $email = $form->get('email');
                
                $errors = array();
                
                if($blahs = $phone->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_phone'] = $temp;
                }
                
                if($blahs = $email->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $this->get('translator')->trans($blah->getMessage(), array(), 'validators');
                    }
                    $errors['fos_user_registration_form_email'] = $temp;
                }
                
                if($blahs = $password->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $this->get('translator')->trans($blah->getMessage(), array(), 'validators');
                    }
                    $errors['fos_user_registration_form_plainPassword_first'] = $temp;
                    $errors['fos_user_registration_form_plainPassword_second'] = $temp;
                }
                
                if($blahs = $firstName->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_firstName'] = $temp;
                }
                
                if($blahs = $lastName->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_lastName'] = $temp;
                }
                
                if($blahs = $state->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_account_state'] = $temp;
                }
                
                if($blahs = $suitcase->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_suitcase'] = $temp;
                }
                
                if($blahs = $zip->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_account_zip'] = $temp;
                }
                
                if($blahs = $terms->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_terms'] = $temp;
                }
                
                if($blahs = $name->getErrors()) {
                    $temp = array();
                    foreach($blahs as $blah) {
                        $temp[] = $blah->getMessage();
                    }
                    $errors['fos_user_registration_form_account_name'] = $temp;
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
    
    protected function getSuitcase() {
        // Establish which suitcase to use for current user
        $user = $this->getUser();
        
        if(!$user) {
            return false;
        }
        
        $company = $user->getCompany();
        $session = $this->getRequest()->getSession();
        $em = $this->getDoctrine()->getManager();
        
        // First, check the current session for a suitcase id
        $sid = $session->get('sid');
        if($sid) {
//echo 'Found SID, step 1: ' . $sid . "<br/>\n";
            $query = $em->createQuery(
                'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.account = :account_id AND s.id = :id')
            ->setParameter('account_id', $company->getId())
            ->setParameter('id', $sid);
            
            try {
                $suitcase = $query->getSingleResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                throw $this->createNotFoundException();
            }
            
            $session->set('token', $suitcase->getToken());
            
            return $suitcase;
        }
        // Second, query for the most recent suitcase (used as default)
        else {
            $query = $em->createQuery(
                'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.account = :account_id'
            )->setParameter('account_id', $company->getId());
            
            try {
                $suitcase = $query->getResult();
            }
            catch (\Doctrine\Orm\NoResultException $e) {
                throw $this->createNotFoundException();
            }
            
            if(count($suitcase) > 0) {
                $suitcase = $suitcase[0];
//                $sid = $suitcase->getId();
//echo 'Found SID, step 2: ' . $sid . "<br/>\n";
                
                
                $session->set('sid', $suitcase->getId());
                $session->set('token', $suitcase->getToken());
                
                return $suitcase;
            }
            else {
                // Third, no existing suitcases found for this account... create a new one
                $suitcase = new Suitcase();
                $suitcase->setAccount($company);
                $suitcase->setToken(base64_encode(sha1(openssl_random_pseudo_bytes(32, $cstrong), true)));
                $em->persist($suitcase);
                $em->flush();
                
//                $sid = $suitcase->getId();
//echo 'Created new SID, step 3: ' . $sid . "<br/>\n";
                
                
                $session->set('sid', $suitcase->getId());
                $session->set('token', $suitcase->getToken());
                
                return $suitcase;
            }
        }
    }
}
