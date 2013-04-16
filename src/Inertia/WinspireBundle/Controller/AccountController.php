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
use Symfony\Bridge\Monolog\Logger;
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
            $formFactory->createNamed('newsletter', 'checkbox', true,
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
                $account->setReferred($form->get('referred')->getData());
                $em->persist($account);
                $em->flush();
                
                $user->setCompany($account);
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
                else {
                    // No packages added to Suitcase (Sign up directly without an item)
                    $response->setData(array(
                        'count' => 0
                    ));
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
    
    
    public function editAction(Request $request)
    {
        $response = new JsonResponse();
        
        if ($request->isMethod('POST')) {
            $em = $this->getDoctrine()->getManager();
            $user = $this->container->get('security.context')->getToken()->getUser();
            
            $contact = $request->request->get('contact');
            
            if(isset($contact['password'])) {
                $password = $contact['password'];
                $oldPassword = $password['old'];
                $newPassword = $password['new'];
                
                if ($user) {
                    // Get the encoder for the users password
                    $encoder_service = $this->get('security.encoder_factory');
                    $encoder = $encoder_service->getEncoder($user);
                    $encoded_pass = $encoder->encodePassword($oldPassword, $user->getSalt());
                    
                    if ($user->getPassword() == $encoded_pass) {
                        $userManager = $this->get('fos_user.user_manager');
                        $user->setPlainPassword($newPassword);
                        $userManager->updateUser($user);
                        
                        return $response->setData(array('success' => true));
                    }
                }
                
                return $response->setData(false);
            }
            else {
                $user->setPhone($contact['phone']);
                
                if(!$this->get('security.context')->isGranted('ROLE_ADMIN')) {
                    $company = $user->getCompany();
                    $company->setAddress($contact['address']);
                    $company->setAddress2($contact['address2']);
                    $company->setCity($contact['city']);
                    $company->setZip($contact['zip']);
                    $company->setState($contact['state']);
                    
                    $em->persist($company);
                }
                $em->persist($user);
                $em->flush();
                
                if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                    return $response->setData(array(
                        'success' => true,
                        'contact' => array(
                            'address' => '',
                            'address2' => '',
                            'city' => '',
                            'state' => '',
                            'zip' => '',
                            'phone' => $user->getPhone()
                        )
                    ));
                }
                else {
                    return $response->setData(array(
                        'success' => true,
                        'contact' => array(
                            'address' => $company->getAddress(),
                            'address2' => $company->getAddress2(),
                            'city' => $company->getCity(),
                            'state' => $company->getState(),
                            'zip' => $company->getZip(),
                            'phone' => $user->getPhone()
                        )
                    ));
                }
            }
        }
        
        return $response->setData(false);
    }
    
    
    public function indexAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();
        
        return $this->render('InertiaWinspireBundle:Account:index.html.twig', array(
            'user' => $user
        ));
    }
    
    
    public function validateEmailAction(Request $request)
    {
        $userManager = $this->get('fos_user.user_manager');
        $response = new JsonResponse();
        
        $form = $request->query->get('fos_user_registration_form');
        $email = $form['email'];
        
        $user = $userManager->findUserByEmail($email);
        if($user) {
            return $response->setData(false);
        }
        else {
            return $response->setData(true);
        }
    }
    
    
    public function validateAction(Request $request)
    {
        $response = new JsonResponse();
        $user = $this->container->get('security.context')->getToken()->getUser();
        $password = $request->query->get('contact');
        $password = $password['password']['old'];
        
        if ($user) {
            // Get the encoder for the users password
            $encoder_service = $this->get('security.encoder_factory');
            $encoder = $encoder_service->getEncoder($user);
            $encoded_pass = $encoder->encodePassword($password, $user->getSalt());
            
            if ($user->getPassword() == $encoded_pass) {
                return $response->setData(true);
            }
        }
        
        return $response->setData(false);
    }
}
