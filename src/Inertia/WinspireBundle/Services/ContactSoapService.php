<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Util\Canonicalizer;
use FOS\UserBundle\Model\UserManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Suitcase;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Security\Core\Util\SecureRandom;

class ContactSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    protected $templating;
    protected $mailer;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordTypeId = '01270000000DVDFAA4';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger, \Swift_Mailer $mailer, EngineInterface $templating, UserManager $userManager)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->templating = $templating;
        $this->mailer = $mailer;
        $this->userManager = $userManager;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('Here comes a Contact update...');
        
        if(!isset($notifications->Notification)) {
            $this->logger->info('notification object is bogus');
            exit;
        }
        
        $ids = array();
        
        if(!is_array($notifications->Notification)) {
            $ids[] = $notifications->Notification->sObject->Id;
        }
        else {
            foreach($notifications->Notification as $n) {
                $ids[] = $n->sObject->Id;
            }
        }
        
        foreach($ids as $id) {
            $this->logger->info('Contact Id: ' . $id);
            
            $contactResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'FirstName, ' .
                'LastName, ' .
                'Title, ' .
                'Phone, ' .
                'Email, ' .
                'AccountId, ' .
                'SystemModstamp ' .
                'FROM Contact ' .
                'WHERE ' .
                'Id =\'' . $id . '\''
            );
            
            // If we don't receive a Contact, then it doesn't meet the criteria
            if(count($contactResult) == 0) {
                $this->logger->info('Contact (' . $id . ') doesn\'t meet the criteria');
                continue;
            }
            
            $sfContact = $contactResult->first();
            
            // Test whether this contact (user) is already in our database
            $user = $this->em->getRepository('InertiaWinspireBundle:User')->findOneBySfId($id);
            
            if(!$user) {
                $this->logger->info('No existing Contact found (' . $id . ')');
                
                // We look for whether this Contact comes from a Partner SF Account
                if ($account = $this->partnerAccount($sfContact->AccountId)) {
                    // Check to make sure the email isn't already used in the system
                    $testUser = $this->em->getRepository('InertiaWinspireBundle:User')->findOneByEmail($sfContact->Email);
                    if ($testUser) {
                        // We've got problems, because someone with this email has already
                        // created an account on the web site.
                        $this->sendForHelp($sfContact->Email);
                        continue;
                    }
                    
                    $generator = new SecureRandom();
                    $random = $generator->nextBytes(10);
                    
                    $user = $this->userManager->createUser();
                    $user->setUsername($sfContact->Email);
                    $user->setEmail($sfContact->Email);
                    $user->setPlainPassword($random);
                    $user->setType('P');
                    $user->setNewsletter(false);
                    $user->setEnabled(true);
                    $user->addRole('ROLE_PARTNER');
                    $user->setCompany($account);
                    $user->setSfId($sfContact->Id);
                    $user->setDirty(false);
                    
                    $this->userManager->updateUser($user);
                    $this->userManager->updatePassword($user);
                    $this->userManager->updateCanonicalFields($user);
                }
                else {
                    continue;
                }
            }
            else {
                // User already exists, just update
                $this->logger->info('Existing Contact (' . $id . ') to be updated');
            }
            
            
            
            // CHANGE CONTACT / USER ACCOUNT
            if(isset($sfContact->AccountId)) {
                $query = $this->em->createQuery(
                    'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId = :sfid'
                )
                    ->setParameter('sfid', $sfContact->AccountId)
                ;
                
                try {
                    $account = $query->getSingleResult();
                    $this->logger->info('    Account: ' . $account->getName());
                    
                    $previousOwner = $user->getCompany()->getSalesperson();
                    $user->setCompany($account);
                    
                    if ($account->getSalesperson()->getUsername() != 'confirmation@winspireme.com' &&
                        $previousOwner->getId() != $account->getSalesperson()->getId()) {
                        // The user was moved to a new Account with a different EC
                        // Send the user an email introduction to their new EC
                        $name = $user->getFirstName() . ' ' .
                            $user->getLastName();
                        
                        $email = $user->getEmail();
                        
                        $locale = strtolower($user->getCompany()->getCountry());
                        
                        $salesperson = array(
                            $user->getCompany()->getSalesperson()->getEmail() =>
                            $user->getCompany()->getSalesperson()->getFirstName() . ' ' .
                            $user->getCompany()->getSalesperson()->getLastName()
                        );
                        
                        $message = \Swift_Message::newInstance()
                            ->setSubject('Introducing your Winspire Event Consultant')
                            ->setReplyTo($salesperson)
                            ->setSender(array('notice@winspireme.com' => 'Winspire'))
                            ->setFrom($salesperson)
                            ->setTo(array($email => $name))
                            ->setBody(
                                $this->templating->render(
                                    'InertiaWinspireBundle:Email:event-consultant-intro.html.twig',
                                    array(
                                        'user' => $user,
                                        'locale' => $locale,
                                    )
                                ),
                                'text/html'
                            )
                            ->addPart(
                                $this->templating->render(
                                    'InertiaWinspireBundle:Email:event-consultant-intro.txt.twig',
                                    array(
                                        'user' => $user,
                                        'locale' => $locale,
                                    )
                                ),
                                'text/plain'
                            )
                        ;
                        $message->setBcc(array($user->getCompany()->getSalesperson()->getEmail()));
                        $this->mailer->send($message);
                    }
                }
                catch (\Exception $e) {
                    $this->logger->err('    Account ID es no bueno: ' . $sfContact->AccountId);
                }
            }
            else {
                $this->logger->err('    Missing AccountID!?!?');
            }
            
            if (($sfContact->SystemModstamp > $user->getSfUpdated()) && !$user->getDirty()) {
                // CONTACT FIRST NAME
                if (isset($sfContact->FirstName)) {
                    $user->setFirstName($sfContact->FirstName);
                }
                
                // CONTACT LAST NAME
                if (isset($sfContact->LastName)) {
                    $user->setLastName($sfContact->LastName);
                }

                // CONTACT TITLE
                if (isset($sfContact->Title)) {
                    $user->setTitle($sfContact->Title);
                }
                
                // CONTACT PHONE
                if (isset($sfContact->Phone)) {
                    $user->setPhone($sfContact->Phone);
                }
                
                // CONTACT EMAIL
                if (isset($sfContact->Email)) {
                    $c = new Canonicalizer();
                    $user->setEmail($sfContact->Email);
                    $user->setUsername($sfContact->Email);
                    $user->setEmailCanonical($c->canonicalize($sfContact->Email));
                    $user->setUsernameCanonical($c->canonicalize($sfContact->Email));
                }
                
                $user->setUpdated($sfContact->SystemModstamp);
                $user->setSfUpdated($sfContact->SystemModstamp);
                
                $this->em->persist($user);
            }
            
            if ($account) {
                $account->setDirty(false);
                
                $timestamp = new \DateTime();
                $account->setSfUpdated($timestamp);
                $account->setUpdated($timestamp);
            }
            
            $this->em->persist($user);
            $this->em->flush();
            
            $this->logger->info('User update saved...');
        }
        
        return array('Ack' => true);
    }
    
    protected function partnerAccount($id)
    {
        $isPartner = false;
        $this->logger->info('Checking Account type for: ' . $id);
        
        // Test whether this account is already in our database
        $account = $this->em->getRepository('InertiaWinspireBundle:Account')->findOneBySfId($id);
        if(!$account) {
            $this->logger->info('No existing Account found (' . $id . ')');
            
            $accountResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'Name, ' .
                'OwnerId, ' .
                'BillingStreet, ' .
                'BillingCity, ' .
                'BillingState, ' .
                'BillingPostalCode, ' .
                'BillingCountry, ' .
                'Phone, ' .
                'Referred_by__c,  ' .
                'White_Label_user__c, ' .
                'White_Label_domain__c, ' .
                'RecordTypeId, ' .
                'SystemModstamp, ' .
                'CreatedDate ' .
                'FROM Account ' .
                'WHERE ' .
                'Id =\'' . $id . '\''
            );
            
            if(count($accountResult) > 0) {
                $sfAccount = $accountResult->first();
                
                if ($sfAccount->RecordTypeId == $this->partnerRecordTypeId) {
                    // Create the Account, since it doesn't already exist here
                    $this->logger->info('New account (' . $id . ') to be added');
                    $account = new Account();
                    $account->setCreated($sfAccount->CreatedDate);
                    $account->setType('P');
                    
                    // ACCOUNT NAME
                    if (isset($sfAccount->Name)) {
                        $account->setName($sfAccount->Name);
                    }
                    $account->setNameCanonical($this->slugify($account->getName()));
                    
                    // ACCOUNT ADDRESS
                    if (isset($sfAccount->BillingStreet)) {
                        $address = explode(chr(10), $sfAccount->BillingStreet);
                        $account->setAddress($address[0]);
                        if (isset($address[1])) {
                            $account->setAddress2($address[1]);
                        }
                    }
                    
                    // ACCOUNT CITY
                    if (isset($sfAccount->BillingCity)) {
                        $account->setCity($sfAccount->BillingCity);
                    }
                    
                    // ACCOUNT STATE
                    if (isset($sfAccount->BillingState)) {
                        $account->setState($sfAccount->BillingState);
                    }
                    
                    // ACCOUNT COUNTRY
                    if (isset($sfAccount->BillingCountry)) {
                        if (strtoupper($sfAccount->BillingCountry) == 'CA' || strtoupper($sfAccount->BillingCountry) == 'CANADA') {
                            $account->setCountry('CA');
                        } elseif (strtoupper($sfAccount->BillingCountry) == 'US' || strtoupper($sfAccount->BillingCountry) == 'UNITED STATES') {
                            $account->setCountry('US');
                        } else {
//                        $account->setCountry($sfAccount->BillingCountry);
                        }
                    } else {
                        $account->setCountry('US');
                    }
                    
                    // ACCOUNT ZIP
                    if (isset($sfAccount->BillingPostalCode)) {
                        $account->setZip($sfAccount->BillingPostalCode);
                    }
                    
                    // ACCOUNT PHONE
                    if (isset($sfAccount->Phone)) {
                        $account->setPhone($sfAccount->Phone);
                    }
                    
                    // ACCOUNT REFERRED
                    if (isset($sfAccount->Referred_by__c)) {
                        $account->setReferred($sfAccount->Referred_by__c);
                    }
                    
                    // ACCOUNT OWNER
                    if (isset($sfAccount->OwnerId)) {
                        $query = $this->em->createQuery(
                            'SELECT u FROM InertiaWinspireBundle:User u WHERE u.sfId = :sfid'
                        )
                        ->setParameter('sfid', $sfAccount->OwnerId);
                        
                        try {
                            $owner = $query->getSingleResult();
                            $account->setSalesperson($owner);
                        } catch (\Exception $e) {
                            $this->logger->err('    Owner ID es no bueno: ' . $sfAccount->OwnerId);
                            $query = $this->em->createQuery(
                                'SELECT u FROM InertiaWinspireBundle:User u WHERE u.id = :id'
                            )
                            ->setParameter('id', 1);
                            $owner = $query->getSingleResult();
                            $account->setSalesperson($owner);
                        }
                    } else {
                        $this->logger->err('    Missing OwnerId?!?!');
                    }
                    
                    $account->setSfId($id);
                    $account->setDirty(false);
                    
                    $account->setSfUpdated($sfAccount->SystemModstamp);
                    $account->setUpdated($sfAccount->SystemModstamp);
                    
                    $this->em->persist($account);
                    $this->em->flush();
                    
                    $this->logger->info('Account saved...');

                    $isPartner = $account;
                }
            }
        }
        else {
            // Account already exists, is it a Partner?
            $this->logger->info('Existing Account found (' . $id . ')');
            if ($account->getType() == 'P') {
                $isPartner = $account;
            }
        }
        
        return $isPartner;
    }
    
    protected function remove_accent($str)
    {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
        return str_replace($a, $b, $str);
    }
    
    protected function slugify($input)
    {
        return strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),
            array('', '-', ''), $this->remove_accent($input)));
    }

    protected function sendForHelp($email)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::NP email already in system')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('doug@inertiaim.com' => 'Douglas Choma'))
            ->setBody('Email: ' . $email . "\n",
                'text/plain'
            )
        ;
        
        $this->mailer->send($message);
    }
}