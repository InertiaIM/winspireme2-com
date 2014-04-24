<?php
namespace Inertia\WinspireBundle\Controller;

use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Security\LoginManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\SuitcaseItem;
use Inertia\WinspireBundle\Form\Type\AccountType;
use Inertia\WinspireBundle\Form\Type\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\True;

class AccountController extends Controller
{
    public function createAction(Request $request)
    {
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

        $accountForm = $this->createForm(new AccountType(), $account, array('auto_initialize' => false,));
        $accountForm->add(
            $formFactory->createNamed('address', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'label' => 'Address Line 1'
                )
            )
        );

        $accountForm->add(
            $formFactory->createNamed('address2', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(),
                    'label' => 'Address Line 2  (Apt., Suite, etc.)',
                    'required' => false
                )
            )
        );

        $accountForm->add(
            $formFactory->createNamed('city', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                )
            )
        );

        $form->add($accountForm);

        $form->add(
            $formFactory->createNamed('firstName', 'text', null,
                array(
                    'auto_initialize' => false,
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
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank()
                    ),
                    'label' => 'Last Name'
                )
            )
        );

        $form->add(
            $formFactory->createNamed('title', 'text', null,
                array(
                    'auto_initialize' => false,
                    'required' => false
                )
            )
        );

        $form->add(
            $formFactory->createNamed('phone', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank(),
                    )
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('newsletter', 'checkbox', true,
                array(
                    'auto_initialize' => false,
                    'label' => 'Please sign me up for the Winspire newsletter, offers and important updates.',
                    'required' => false
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('event_name', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank(),
                        new Length(array('min' => 3))
                    ),
                    'label' => 'Name of Event',
                    'mapped' => false,
                    'required' => true
                )
            )
        );
        
        $form->add(
            $formFactory->createNamed('event_date', 'text', null,
                array(
                    'auto_initialize' => false,
                    'constraints' => array(
                        new NotBlank(),
                    ),
                    'label' => 'Date of Event',
                    'mapped' => false,
                    'required' => true
                )
            )
        );

        $form->add(
            $formFactory->createNamed('referred', 'text', null,
                array(
                    'auto_initialize' => false,
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
                    'auto_initialize' => false,
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
                    'auto_initialize' => false,
                    'mapped' => false,
                    'required' => false
                )
            )
        );

        if ($request->query->has('package')) {
            $form->get('package')->setData($request->query->get('package'));
        }
        
        if ($request->getLocale() == 'ca') {
            $form->get('account')->get('country')->setData('CA');
        }
        else {
            $form->get('account')->get('country')->setData('US');
        }

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
                $account->setState(substr($form->get('account')->get('state')->getData(), -2));
                $account->setDirty(true);
                $em->persist($account);
                $em->flush();

                $user->setCompany($account);
                $user->setDirty(true);
                $userManager->updateUser($user);

                $suitcase = new Suitcase();
                $suitcase->setStatus('U');
                $suitcase->setName($form->get('event_name')->getData());
                $suitcase->setEventName($form->get('event_name')->getData());
                $suitcase->setEventDate(new \DateTime($form->get('event_date')->getData()));
                $suitcase->setDirty(true);
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
                        $suitcaseItem->setQuantity(0);
                        $suitcaseItem->setPrice(0);
                        $suitcaseItem->setSubtotal(0);
                        $suitcaseItem->setCost(0);
                        $suitcaseItem->setStatus('M');

                        $suitcase->addItem($suitcaseItem);
                        $em->persist($suitcaseItem);
                    }
                    catch (\Doctrine\Orm\NoResultException $e) {
                    }
                }

                $em->persist($suitcase);
                $em->flush();

                try {
                    $msg = array('suitcase_id' => $suitcase->getId());
                    $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'create-account');
                }
                catch (\Exception $e) {
                    $this->get('logger')->err('Rabbit queue (create-account) es no bueno!');
                }


                $loginManager->loginUser('main', $user);

                $session->set('sid', $suitcase->getId());

                return $this->redirect($this->generateUrl('suitcaseView'));
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
                    $company->setState(substr($contact['state'], -2));
                    $company->setCountry($contact['country']);
                    $company->setDirty(true);
                    
                    $em->persist($company);
                }
                $em->persist($user);
                $em->flush();
                
                $msg = array('id' => $user->getId(), 'type' => 'account');
                $this->get('old_sound_rabbit_mq.winspire_producer')->publish(serialize($msg), 'update-sf');
                
                if($this->get('security.context')->isGranted('ROLE_ADMIN')) {
                    return $response->setData(array(
                        'success' => true,
                        'contact' => array(
                            'address' => '',
                            'address2' => '',
                            'city' => '',
                            'state' => '',
                            'country' => '',
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
                            'country' => $company->getCountry() == 'CA' ? 'Canada' : 'United States',
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
        
        // TODO move this into the Suitcase Manager service?
        $activeCount = 0;
        $historyCount = 0;
        foreach ($user->getSuitcases() as $suitcase) {
            if ($suitcase->getStatus() == 'M') {
                $historyCount++;
            }
            else {
                $activeCount++;
            }
        }
        
        return $this->render('InertiaWinspireBundle:Account:index.html.twig', array(
            'user' => $user,
            'historyCount' => $historyCount,
            'activeCount' => $activeCount
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
