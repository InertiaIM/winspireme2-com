<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\User;
use Symfony\Bridge\Monolog\Logger;

class AccountSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    
    private $recordTypeId = '01270000000DVD5AAO';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('Here comes an Account update...');
        
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
            $this->logger->info('Account Id: ' . $id);
            
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
                'RecordTypeId ' .
                'FROM Account ' .
                'WHERE ' .
                'RecordTypeId = \'' . $this->recordTypeId . '\'' .
                'AND Id =\'' . $id . '\''
            );
            
            // If we don't receive an Account, then it doesn't meet the criteria
            if(count($accountResult) == 0) {
                $this->logger->info('Account (' . $id . ') doesn\'t meet the criteria');
                continue;
            }
            
            
            // Test whether this package is already in our database
            $account = $this->em->getRepository('InertiaWinspireBundle:Account')->findOneBySfId($id);
            
            if(!$account) {
                // New account, not in our database yet
                $this->logger->info('New account (' . $id . ') to be added');
                $account = new Account();
                $new = true;
            }
            else {
                // Account already exists, just update
                $this->logger->info('Existing account (' . $id . ') to be updated');
                $new = false;
            }
            
            
            $a = $accountResult->first();
            
            // ACCOUNT NAME
            if(isset($a->Name) && $new) {
                $account->setName($a->Name);
            }
            $account->setNameCanonical($this->slugify($account->getName()));
            
            // ACCOUNT ADDRESS
            if(isset($a->BillingStreet) && $new) {
// TODO NEED TO SPLIT STREET into TWO
                $account->setAddress($a->BillingStreet);
            }
            
            // ACCOUNT CITY
            if(isset($a->BillingCity) && $new) {
                $account->setCity($a->BillingCity);
            }
            
            // ACCOUNT STATE
            if(isset($a->BillingState) && $new) {
                $account->setState($a->BillingState);
            }
            
            // ACCOUNT ZIP
            if(isset($a->BillingPostalCode) && $new) {
                $account->setZip($a->BillingPostalCode);
            }
            
            // ACCOUNT PHONE
            if(isset($a->Phone) && $new) {
                $account->setPhone($a->Phone);
            }
            
            // ACCOUNT REFERRED
            if(isset($a->Referred_by__c) && $new) {
                $account->setReferred($a->Referred_by__c);
            }
            
            // ACCOUNT OWNER
            if(isset($a->OwnerId)) {
                $query = $this->em->createQuery(
                    'SELECT u FROM InertiaWinspireBundle:User u WHERE u.sfId = :sfid'
                )
                    ->setParameter('sfid', $a->OwnerId)
                ;
                
                try {
                    $owner = $query->getSingleResult();
//                    $output->writeln('<info>    Owner: ' .  $owner->getEmail() . '</info>');
                    $account->setSalesperson($owner);
                }
                catch (\Exception $e) {
//                    $output->writeln('<error>    Owner ID es no bueno: ' . $sfAccount->OwnerId . '</error>');
                    $query = $this->em->createQuery(
                        'SELECT u FROM InertiaWinspireBundle:User u WHERE u.id = :id'
                    )
                        ->setParameter('id', 1)
                    ;
                    $owner = $query->getSingleResult();
                    $account->setSalesperson(owner);
                }
            }
            else {
//                $output->writeln('<error>    Missing OwnerId?!?!</error>');
            }
            
            $account->setSfId($id);
            $account->setDirty(false);
            
            $timestamp = new \DateTime();
            $account->setSfUpdated($timestamp);
            $account->setUpdated($timestamp);
            
            $this->em->persist($account);
            $this->em->flush();
            
            $this->logger->info('Account saved...');
        }
        
        return array('Ack' => true);
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
}