<?php
namespace Inertia\WinspireBundle\Consumer;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Templating\EngineInterface;

class CreateAccountConsumer implements ConsumerInterface
{
    protected $em;
    protected $mailer;
    protected $templating;
    protected $sf;
    protected $producer;
    
    private $recordTypeId = '01270000000DVD5AAO';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    private $contactRecordId = '01270000000MzR9AAK';
    
    public function __construct(
        EntityManager $entityManager,
        \Swift_Mailer $mailer,
        EngineInterface $templating,
        Client $salesforce,
        Producer $producer
    )
    {
        $this->em = $entityManager;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->sf = $salesforce;
        $this->producer = $producer;
        
        $this->mailer->getTransport()->stop();

        try {
            $this->sf->logout();
        }
        catch (\Exception $e) {
            $this->sendForHelp('(_construct): ' . $e->getMessage());
        }
    }
    
    public function execute(AMQPMessage $msg)
    {
        $body = unserialize($msg->body);
        $suitcaseId = $body['suitcase_id'];
        
        $query = $this->em->createQuery(
            'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WITH i.status != \'X\' WHERE s.id = :id ORDER BY i.updated DESC'
        )->setParameter('id', $suitcaseId);
        
        try {
            $suitcase = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            // If we can't get the Suitcase record we'll 
            // throw out the message from the queue (ack)
            return true;
        }
        
        $user = $suitcase->getUser();
        
        
        // Salesforce Updates
        $account = $user->getCompany();
        if ($account->getSfId() == '') {
            $createNew = true;
//echo $this->slugify($account->getName()) . "\n";
            $account->setNameCanonical($this->slugify($account->getName()));
            
            // Attempt a match with an existing SF account/contact pair
            try {
                $contactResult = $this->sf->query('SELECT ' .
                    'Id, ' .
                    'Email, ' .
                    'SystemModstamp, ' .
                    'AccountId ' .
                    'FROM Contact ' .
                    'WHERE Email = \'' . $user->getEmailCanonical() . '\' ' .
                    'ORDER BY CreatedDate ASC'
                );
            }
            catch (\Exception $e) {
                $this->sendForHelp('(5): ' . $e->getMessage());
                $this->sf->logout();
                
                return false;
            }
            
            // If a contact record is found, check with the existing Account records
            if (count($contactResult) > 0) {
//echo 'We\'re matched on email' . "\n";
                foreach ($contactResult as $sfContact) {
                    // Check our Account table for an existing match
                    $existingAccount = $this->em->getRepository('InertiaWinspireBundle:Account')->findOneBySfId($sfContact->AccountId);
                    if ($existingAccount) {
//echo 'We\'ve found an existing account, let\'s see if the names match...' . "\n";
                        if ($account->getNameCanonical() == $existingAccount->getNameCanonical()) {
//echo 'We\'re matched on email + account' . "\n";
                            if (strtoupper($account->getState()) == $existingAccount->getState()) {
//echo 'We\'re matched on email + account + state' . "\n";
//echo 'Reassigning to existing Account' . "\n";
                                $user->setCompany($existingAccount);
                                $user->setSfId($sfContact->Id);
                                $user->setDirty(false);
                                unset($sfContact->SystemModstamp);
                                unset($sfContact->Email);
                                unset($sfContact->AccountId);
                                $sfContact->FirstName = $user->getFirstName();
                                $sfContact->LastName = $user->getLastName();
                                $sfContact->Title = $user->getTitle();
                                $sfContact->Phone = $user->getPhone();
                                
                                try {
                                    $this->sf->update(array($sfContact), 'Contact');
                                }
                                catch (\Exception $e) {
                                    $this->sendForHelp('(4): ' . $e->getMessage());
                                    $this->sf->logout();
                                    
                                    return false;
                                }
                                
                                
                                $timestamp = new \DateTime();
                                $user->setSfUpdated($timestamp);
                                $user->setUpdated($timestamp);
                                $this->em->persist($user);
                                $this->em->remove($account);
                                $this->em->flush();
                                
                                $account = $user->getCompany();
                                $createNew = false;
                                break;
                            }
                            else {
//echo 'No match on email + account + state' . "\n";
                            }
                        }
                        else {
//echo 'No match on email + account' . "\n";
                        }
                    }
                    else {
                        // Attempt a match with an existing SF account
                        // (not sure why we'd find an account not in our db, but just in case...)
                        try {
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
                                'RecordTypeId, ' .
                                'SystemModstamp, ' .
                                'CreatedDate ' .
                                'FROM Account ' .
                                'WHERE ' .
                                'RecordTypeId = \'' . $this->recordTypeId . '\'' .
                                'AND Id =\'' . $sfContact->AccountId . '\''
                            );
                        }
                        catch (\Exception $e) {
                            $this->sendForHelp('(3): ' . $e->getMessage());
                            $this->sf->logout();
                            
                            return false;
                        }
                        
                        if (count($accountResult) > 0) {
//echo 'An account was found in SF that\'s not already in our Account table' . "\n";
                            $sfAccount = $accountResult->first();
                            if ($this->slugify($sfAccount->Name) == $account->getNameCanonical()) {
                                if (strtoupper($sfAccount->BillingState) == $account->getState()) {
//echo 'We\'ve found a matched Account that\'s not already in our Account table' . "\n";
                                    $account->setSfId($sfAccount->Id);
                                    $account->setCreated($sfAccount->CreatedDate);
                                    
                                    // ACCOUNT ADDRESS
                                    if(isset($sfAccount->BillingStreet)) {
                                        $address = explode(chr(10), $sfAccount->BillingStreet);
                                        $account->setAddress($address[0]);
                                        if (isset($address[1])) {
                                            $account->setAddress2($address[1]);
                                        }
                                    }
                                    
                                    // ACCOUNT CITY
                                    if(isset($sfAccount->BillingCity)) {
                                        $account->setCity($sfAccount->BillingCity);
                                    }
                                    
                                    // ACCOUNT ZIP
                                    if(isset($sfAccount->BillingPostalCode)) {
                                        $account->setZip($sfAccount->BillingPostalCode);
                                    }
                                    
                                    // ACCOUNT PHONE
                                    if(isset($sfAccount->Phone)) {
                                        $account->setPhone($sfAccount->Phone);
                                    }
                                    
                                    // ACCOUNT COUNTRY
                                    if(isset($sfAccount->BillingCountry)) {
                                        if (strtoupper($sfAccount->BillingCountry) == 'CA' || strtoupper($sfAccount->BillingCountry) == 'CANADA') {
                                            $account->setCountry('CA');
                                        }
                                        elseif (strtoupper($sfAccount->BillingCountry) == 'US' || strtoupper($sfAccount->BillingCountry) == 'UNITED STATES') {
                                            $account->setCountry('US');
                                        }
                                        else {
//                                            $account->setCountry($sfAccount->BillingCountry);
                                        }
                                    }
                                    else {
                                        $account->setCountry('US');
                                    }
                                    
                                    // ACCOUNT OWNER
                                    if(isset($sfAccount->OwnerId)) {
                                        $query = $this->em->createQuery(
                                            'SELECT u FROM InertiaWinspireBundle:User u WHERE u.sfId = :sfid'
                                        )
                                        ->setParameter('sfid', $sfAccount->OwnerId)
                                        ;
                                        
                                        try {
                                            $owner = $query->getSingleResult();
                                            $account->setSalesperson($owner);
                                        }
                                        catch (\Exception $e) {
                                            $query = $this->em->createQuery(
                                                'SELECT u FROM InertiaWinspireBundle:User u WHERE u.id = :id'
                                            )
                                            ->setParameter('id', 1)
                                            ;
                                            $owner = $query->getSingleResult();
                                            $account->setSalesperson($owner);
                                        }
                                    }
                                    
                                    $account->setSfUpdated($sfAccount->SystemModstamp);
                                    $account->setUpdated($sfAccount->SystemModstamp);
                                    unset($sfContact->SystemModstamp);
                                    unset($sfContact->Email);
                                    unset($sfContact->AccountId);
                                    $sfContact->FirstName = $user->getFirstName();
                                    $sfContact->LastName = $user->getLastName();
                                    $sfContact->Title = $user->getTitle();
                                    $sfContact->Phone = $user->getPhone();
                                    
                                    try {
                                        $this->sf->update(array($sfContact), 'Contact');
                                    }
                                    catch (\Exception $e) {
                                        $this->sendForHelp('(2): ' . $e->getMessage());
                                        $this->sf->logout();
                                        
                                        return false;
                                    }
                                    
                                    $timestamp = new \DateTime();
                                    $user->setSfId($sfContact->Id);
                                    $user->setDirty(false);
                                    $user->setSfUpdated($timestamp);
                                    $user->setUpdated($timestamp);
                                    $this->em->persist($user);
                                    $createNew = false;
                                    break;
                                }
                            }
                        }
                        else {
//echo 'Bad place to be... we have a Contact in SF with a bogus Account Id? OR a match to a PARTNER account' . "\n";
                        }
                    }
                }
            }
            
            if ($createNew) {
//echo 'No matching Account found, so we\'re creating a new one...' . "\n";
                $address = $account->getAddress();
                if ($account->getAddress2() != '') {
                    $address .= chr(10) . $account->getAddress2();
                }
                
                $sfAccount = new \stdClass();
                $sfAccount->Name = $account->getName();
                $sfAccount->BillingStreet = $address;
                $sfAccount->BillingCity = $account->getCity();
                $sfAccount->BillingState = $account->getState();
                $sfAccount->BillingPostalCode = $account->getZip();
                $sfAccount->BillingCountryCode = $account->getCountry();
                $sfAccount->BillingCountry = ($account->getCountry() == 'CA' ? 'Canada' : 'United States');
                $sfAccount->Phone = $account->getPhone();
                $sfAccount->Referred_by__c = substr($account->getReferred(), 0, 50);
                $sfAccount->RecordTypeId = $this->recordTypeId;
                $sfAccount->OwnerId = $account->getSalesperson()->getSfId();
                $sfAccount->Event_Type_Unknown__c = true;
                if ($suitcase->getEventDate() != '') {
                    $sfAccount->Event_Month_Unknown__c = $suitcase->getEventDate()->format('F');
                }
                else {
                    $temp = new \DateTime('+30 days');
                    $sfAccount->Event_Month_Unknown__c = $temp->format('F');
                }
                $sfAccount->Item_Use__c = 'Unknown';
                
                try {
                    $saveResult = $this->sf->create(array($sfAccount), 'Account');
                }
                catch (\Exception $e) {
                    $this->sendForHelp('(1): ' . $e->getMessage());
                    $this->sf->logout();
                    
                    return false;
                }
                
                if($saveResult[0]->success) {
                    $timestamp = new \DateTime();
                    $account->setSfId($saveResult[0]->id);
                    $account->setSfUpdated($timestamp);
                    $account->setUpdated($timestamp);
                    $account->setCreated($timestamp);
                }
            }
            
            $account->setDirty(false);
            $this->em->persist($account);
            $this->em->flush();
        }
        
        if ($user->getSfId() == '' && $account->getSfId() != '') {
            $sfContact = new \stdClass();
            $sfContact->FirstName = $user->getFirstName();
            $sfContact->LastName = $user->getLastName();
            $sfContact->Title = $user->getTitle();
            $sfContact->Phone = $user->getPhone();
            $sfContact->Email = $user->getEmail();
            $sfContact->AccountId = $account->getSfId();
            $sfContact->Default_contact__c = 1;
            $sfContact->OwnerId = $account->getSalesperson()->getSfId();
            $sfContact->LeadSource = 'TBD';
            $sfContact->RecordTypeId = $this->contactRecordId;
            
            try {
                $saveResult = $this->sf->create(array($sfContact), 'Contact');
            }
            catch (\Exception $e) {
                $this->sendForHelp('(0): ' . $e->getMessage());
                $this->sf->logout();
                
                return false;
            }
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $user->setSfId($saveResult[0]->id);
                $user->setDirty(false);
                $user->setSfUpdated($timestamp);
                $user->setUpdated($timestamp);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
        
        if ($user->getSfId() != '' && $account->getSfId() != '') {
            $sfContact = new \stdClass();
            $sfContact->Id = $user->getSfId();
            
            $saveResult = $this->sf->update(array($sfContact), 'Contact');
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $user->setSfId($saveResult[0]->id);
                $user->setDirty(false);
                $user->setSfUpdated($timestamp);
                $user->setUpdated($timestamp);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
        
        if ($suitcase->getSfId() == '' && $user->getSfId() != '') {
            $sfOpportunity = new \stdClass();
            $sfOpportunity->CloseDate = new \DateTime('+60 days');
            $sfOpportunity->Name = $suitcase->getName();
            $sfOpportunity->StageName = 'Counsel';
            $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
            if ($suitcase->getEventName() != '') {
                $sfOpportunity->Event_Name__c = $suitcase->getEventName();
            }
            else {
                $sfOpportunity->Event_Name__c = '';
            }
            if ($suitcase->getEventDate() != '') {
                $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
            }
            else {
                $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
            }
            $sfOpportunity->AccountId = $account->getSfId();
            $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
            $sfOpportunity->LeadSource = 'TBD';
            $sfOpportunity->Type = 'Web Suitcase';
            $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
            $sfOpportunity->Item_Use__c = 'Unknown';
            $sfOpportunity->Event_Type__c = 'Unknown';
            $sfOpportunity->OwnerId = $account->getSalesperson()->getSfId();
            
            try {
                $saveResult = $this->sf->create(array($sfOpportunity), 'Opportunity');
            }
            catch (\Exception $e) {
                $this->sendForHelp('(-1): ' . $e->getMessage());
                $this->sf->logout();
                
                return false;
            }
            
            if($saveResult[0]->success) {
                $timestamp = new \DateTime();
                $suitcase->setSfId($saveResult[0]->id);
                $suitcase->setDirty(false);
                $suitcase->setSfUpdated($timestamp);
                $suitcase->setUpdated($timestamp);
                $this->em->persist($suitcase);
                $this->em->flush();
                
                $msg = array('id' => $suitcase->getId(), 'type' => 'suitcase-items');
                $this->producer->publish(serialize($msg), 'update-sf');
            }
        }
        
        
        // Send Mail Messages
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Welcome to Winspire!')
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail()))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:create-suitcase-welcome.html.twig',
                    array(
                        'user' => $suitcase->getUser(),
                        'suitcase' => $suitcase,
                        'locale' => $locale,
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:create-suitcase-welcome.txt.twig',
                    array(
                        'user' => $suitcase->getUser(),
                        'suitcase' => $suitcase,
                        'locale' => $locale
                    )
                ),
                'text/plain'
            )
        ;
        
        // If the new user is assigned to a real EC, we'll send the intro email now
        $message2 = false;
        if ($user->getCompany()->getSalesperson()->getUsername() != 'confirmation@winspireme.com') {
            $salesperson = array(
                $suitcase->getUser()->getCompany()->getSalesperson()->getEmail() =>
                $suitcase->getUser()->getCompany()->getSalesperson()->getFirstName() . ' ' .
                $suitcase->getUser()->getCompany()->getSalesperson()->getLastName()
            );
            
            $message2 = \Swift_Message::newInstance()
                ->setSubject('Introducing your Winspire Event Consultant')
                ->setReplyTo($salesperson)
                ->setSender(array('info@winspireme.com' => 'Winspire'))
                ->setFrom($salesperson)
//                ->setTo(array($email => $name))
                ->setTo(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail()))
                ->setBody(
                    $this->templating->render(
                        'InertiaWinspireBundle:Email:event-consultant-intro.html.twig',
                        array(
                            'user' => $suitcase->getUser(),
                            'from' => $suitcase->getUser()->getCompany()->getSalesperson()->getEmail(),
                            'locale' => $locale,
                        )
                    ),
                    'text/html'
                )
                ->addPart(
                    $this->templating->render(
                        'InertiaWinspireBundle:Email:event-consultant-intro.txt.twig',
                        array(
                            'user' => $suitcase->getUser(),
                            'from' => $suitcase->getUser()->getCompany()->getSalesperson()->getEmail(),
                            'locale' => $locale,
                        )
                    ),
                    'text/plain'
                )
            ;
        }
        
        
        $this->em->clear();
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        
        if ($message2) {
            $this->mailer->send($message2);
        }
        
        $this->mailer->getTransport()->stop();
        
        try {
            $this->sf->logout();
        }
        catch (\Exception $e) {
            $this->sendForHelp('(-2): ' . $e->getCode());
        }
        
        return true;
    }
    
    protected function remove_accent($str)
    {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
        return str_replace($a, $b, $str);
    }
    
    protected function sendForHelp($text)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire::Debug for Create Account')
            ->setFrom(array('notice@winspireme.com' => 'Winspire'))
            ->setTo(array('iim@inertiaim.com' => 'Inertia-IM'))
            ->setBody(
                $text,
                'text/plain'
            )
        ;
        
        $this->mailer->getTransport()->start();
        $this->mailer->send($message);
        $this->mailer->getTransport()->stop();
        
        $this->em->clear();
        $this->em->getConnection()->close();
    }
    
    protected function slugify($input)
    {
        return strtolower(preg_replace(array('/[^a-zA-Z0-9 -]/', '/[ -]+/', '/^-|-$/'),
            array('', '-', ''), $this->remove_accent($input)));
    }
}