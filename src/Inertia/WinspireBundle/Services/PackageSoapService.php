<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Package;
use Search\SphinxsearchBundle\Services\Indexer\Indexer;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Templating\EngineInterface;

class PackageSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    protected $indexer;
    protected $mailer;
    protected $templating;
    
    private $pricebookId = '01s700000006IU7AAM';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger, Indexer $indexer, \Swift_Mailer $mailer, EngineInterface $templating)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->indexer = $indexer;
        $this->mailer = $mailer;
        $this->templating = $templating;
    }
    
    public function notifications($notifications)
    {
        $this->logger->info('It\'s big, it\'s heavy, it\'s wood');
        
$dump = fopen('wtf.log', 'a');
fwrite($dump, print_r($notifications, true));
        
        
        
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
            $this->logger->info('id: ' . $id);
            
            $packageResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'Name, ' .
                'ProductCode, ' .
                'WEB_package_subtitle__c, ' .
                'WEB_package_description__c, ' .
                'Keyword_search__c, ' .
                'Location__c, ' .
                'Content_PACK__c, ' .
                'Year_Version__c, ' .
                'Suggested_Retail_Value__c, ' .
                'Home_Page_view__c, ' .
                'Package_Category_Pairings__c, ' .
                'New_Item__c, ' .
                'Best_Seller__c, ' .
                'WEB_Default_version__c, ' .
                'Parent_Header__c, ' .
                'IsActive, ' .
                'Available__c, ' .
                'WEB_picture__c, ' .
                'WEB_thumbnail__c, ' .
                'WEB_picture_title__c, ' .
                'Web_URL_slug__c, ' .
                'WEB_seasonal_pkg__c, ' .
                'WEB_meta_title__c, ' .
                'WEB_meta_description__c, ' .
                'WEB_meta_keywords__c, ' .
                'WEB_Airfare_pax__c, ' .
                'WEB_Nights__c, ' .
                'WEB_Participants__c, ' .
                'WEB_Recommendations__c, ' .
                'OMIT_from_Winspire__c, ' .
                'SystemModstamp ' .
                'FROM Product2 ' .
                'WHERE ' .
                'family = \'No-Risk Auction Package\' ' .
                'AND IsDeleted = false ' .
                'AND WEB_package_subtitle__c != \'\' ' .
                'AND Parent_Header__c != \'\' ' .
                'AND Id = \''. $id . '\''
            );
            
            // If we don't receive a Package, then it doesn't meet the criteria
            if(count($packageResult) == 0) {
                $this->logger->info('Package (' . $id . ') doesn\'t meet the criteria');
                continue;
            }
            
            
            $p = $packageResult->first();
            
            // Test whether this package is already in our database
            $package = $this->em->getRepository('InertiaWinspireBundle:Package')->findOneBySfId($id);
            
            if(!$package) {
                // New package, not in our database yet
                $this->logger->info('New package (' . $id . ') to be added');
                
                $package = new Package();
            }
            else {
                // Package already exists, just update
                $this->logger->info('Existing package (' . $id . ') to be updated');
            }
            
            if (isset($p->WEB_package_subtitle__c)) {
                $package->setName($p->WEB_package_subtitle__c);
            }
            $package->setParentHeader($p->Parent_Header__c);
            $package->setCode($p->ProductCode);
            $package->setSfId($p->Id);
            $package->setIsOnHome($p->Home_Page_view__c);
            $package->setIsBestSeller($p->Best_Seller__c);
            $package->setIsNew($p->New_Item__c);
            $package->setSeasonal($p->WEB_seasonal_pkg__c);
            $package->setIsDefault($p->WEB_Default_version__c);
            $package->setSuggestedRetailValue($p->Suggested_Retail_Value__c);
            
            if (isset($p->Year_Version__c)) {
                $package->setYearVersion($p->Year_Version__c);
            }
            
            if(isset($p->OMIT_from_Winspire__c)) {
                $package->setIsPrivate($p->OMIT_from_Winspire__c == '1' ? true : false);
            }
            else {
                $package->setIsPrivate(false);
            }
            
            if(isset($p->IsActive)) {
                $processRemoval = false;
                if ($package->getActive() && ($p->IsActive == '0')) {
                    // Changing from Active -> Inactive
                    $processRemoval = true;
                }
                
                $package->setActive($p->IsActive == '1' ? true : false);
                if (!$package->getActive()) {
                    $this->logger->info('INACTIVE package (' . $id . ')');
                }
                
                if ($processRemoval) {
                    $this->logger->info('Process Removal of package due to INACTIVE (' . $id . ')');
                    
                    // TODO is it necessary to delete the package even if it's possible?
                    $deletePackage = $this->processRemoval($package);
                }
            }
            
            if (isset($p->Available__c)) {
                $processRemoval = false;
                if ($package->getAvailable() && ($p->Available__c == 'NO')) {
                    // Changing from Available -> Unavailable
                    $processRemoval = true;
                }
                
                $package->setAvailable($p->Available__c == 'NO' ? false : true);
                if (!$package->getAvailable()) {
                    $this->logger->info('UNAVAILABLE package (' . $id . ')');
                }
                
                if ($processRemoval) {
                    $this->logger->info('Process Removal of package due to UNAVAILABLE (' . $id . ')');
                    
                    // TODO is it necessary to delete the package even if it's possible?
                    $deletePackage = $this->processRemoval($package);
                }
            }
            else {
                $package->setAvailable(true);
            }
            
            if(isset($p->WEB_package_description__c)) {
                // Split the description into two based on the "more" tag
                $details = preg_split ('/\<\!--.*more.* --\>/i', $p->WEB_package_description__c);
                
                $package->setDetails(trim($details[0]));
                if(isset($details[1])) {
                    $package->setMoreDetails(trim($details[1]));
                }
            }
            
            if(isset($p->Web_URL_slug__c)) {
                $package->setSlug($p->Web_URL_slug__c);
            }
            else {
                $package->setSlug($this->slugify($package->getParentHeader()));
            }
            
            if(isset($p->WEB_Airfare_pax__c)) {
                $package->setAirfares($p->WEB_Airfare_pax__c);
            }
            else {
                $package->setAirfares(0);
            }
            
            if(isset($p->WEB_Nights__c)) {
                $package->setAccommodations($p->WEB_Nights__c);
            }
            else {
                $package->setAccommodations(0);
            }
            
            if(isset($p->WEB_Participants__c)) {
                $package->setPersons($p->WEB_Participants__c);
            }
            else {
                $package->setPersons(0);
            }
            
            if(isset($p->WEB_picture__c)) {
                $package->setPicture($p->WEB_picture__c);
            }
            else {
                $package->setPicture('0000_Winspire-Oops-Twins-MAIN.jpg');
            }
            
            if(isset($p->WEB_thumbnail__c)) {
                $package->setThumbnail($p->WEB_thumbnail__c);
            }
            else {
                $package->setThumbnail('0000_Winspire-Oops-Twins-THUMB.jpg');
            }
            
            if(isset($p->WEB_picture_title__c)) {
                $package->setPictureTitle($p->WEB_picture_title__c);
            }
            
            if(isset($p->WEB_meta_title__c)) {
                $package->setMetaTitle($p->WEB_meta_title__c);
            }
            
            if(isset($p->WEB_meta_description__c)) {
                $package->setMetaDescription($p->WEB_meta_description__c);
            }
            
            if(isset($p->WEB_meta_keywords__c)) {
                $package->setMetaKeywords($p->WEB_meta_keywords__c);
            }
            
            if(isset($p->Content_PACK__c)) {
                $package->setContentPack($p->Content_PACK__c);
            }
            
            if(isset($p->Keyword_search__c)) {
                $keywords = explode(';', $p->Keyword_search__c);
                
                foreach($keywords as $i => $k) {
                    $keywords[$i] = trim($k);
                }
                
                $package->setKeywords(implode(' ', $keywords));
            }
            
            // TODO do we have to remove each recommendation manually?
            // TODO create "merge" recommendation method?
            foreach($package->getRecommendations() as $r) {
                $package->removeRecommendation($r);
            }
            if(isset($p->WEB_Recommendations__c)) {
                $recommendations = explode(';', $p->WEB_Recommendations__c);
                
                foreach($recommendations as $r) {
                    $recommended = $this->findPackageByCode(trim($r));
                    if ($recommended) {
                        $package->addRecommendation($recommended);
                    }
                }
            }
            
            
            $categories = array();
            if(isset($p->Package_Category_Pairings__c)) {
                $categories = explode(';', $p->Package_Category_Pairings__c);
            }
            
            // TODO do we have to remove each category manually
            // TODO create "merge" category method
            foreach($package->getCategories() as $c) {
                $package->removeCategory($c);
            }
            
            foreach($categories as $category) {
                $cat = $this->findCategoryByCode(trim($category));
                if ($cat) {
                    $package->addCategory($cat);
                }
            }
            
            
            $this->logger->info('Starting pricebook lookup...');
            
            $pricebookResult = $this->sf->query('SELECT ' .
                'Id, ' .
                'Name, ' .
                'UnitPrice ' .
                'FROM PricebookEntry ' .
                'WHERE Pricebook2Id = \'' . $this->pricebookId . '\' ' .
                'AND Product2Id = \'' . $id . '\''
            );
            
            if(count($pricebookResult) < 1) {
                $this->logger->info('No prices available, so we\'re not saving this Package');
                continue;
            }
            
            $pricebookEntry = $pricebookResult->first();
            $package->setSfPricebookEntryId($pricebookEntry->Id);
            $package->setCost($pricebookEntry->UnitPrice);
            $package->setUpdated($p->SystemModstamp);
            
            
            $this->em->persist($package);
            $this->em->flush();
            
            $this->logger->info('Package saved...');
        }
        
        $this->indexer->rotate('Packages');
        
        return array('Ack' => true);
    }
    
    
    protected function findPackageByCode($code)
    {
        $query = $this->em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.code = :code AND p.active = 1 ORDER BY p.created DESC'
        )
            ->setParameter('code', $code)
            ->setMaxResults(1);
        ;
        
        try {
            $package = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            $package = false;
        }
        
        return $package;
    }
    
    
    protected function findCategoryByCode($code)
    {
        $query = $this->em->createQuery(
            'SELECT c FROM InertiaWinspireBundle:Category c WHERE c.number = :code'
        )
            ->setParameter('code', $code)
        ;
        
        try {
            $category = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
//            echo 'problem with category lookup';
            $category = false;
        }
        
        return $category;
    }
    
    protected function getAssociatedSuitcases($package) {
        $suitcases = array();
        foreach ($package->getSuitcaseItems() as $item) {
            $this->logger->info('Retrieving Associated Suitcase: ' . $item->getSuitcase()->getId());
            $suitcases[] = $item->getSuitcase();
        }
        
        return $suitcases;
    }
    
    protected function processRemoval($package) {
        $suitcases = $this->getAssociatedSuitcases($package);
        
        if (count($suitcases) == 0) {
            $this->logger->info('No Suitcases to worry about for Package removal');
            // No Suitcases contain this Package so it's safe
            // to delete without further processing
            $message = \Swift_Message::newInstance()
                ->setSubject('Winspire::Package deactivated')
                ->setFrom(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array('doug@inertiaim.com'))
                ->setBody('No Suitcases were effected.' . "\n" .
                    'Package ID: ' . $package->getId() . "\n" .
                    'SF ID: ' . $package->getSfId() . "\n",
                'text/plain')
            ;
            $this->mailer->send($message);
            
            return true;
        }
        else {
            foreach ($suitcases as $suitcase) {
                if ($suitcase->getStatus() == 'U') {
                    // Unpacked Suitcase
                    // Remove the item from the Suitcase and email NP
                    $this->logger->info('Unpacked Suitcase contains inactive package: ' . $suitcase->getId());
                    
                    $sendMessage = true;
                    foreach ($suitcase->getItems() as $item) {
                        if ($item->getPackage()->getId() == $package->getId()) {
                            // Only send a message if NP hasn't marked the package for deletion,
                            // and the event date is in the future...
                            if ($item->getStatus() == 'X' || ($suitcase->getEventDate() < new \DateTime())) {
                                $sendMessage = false;
                                $this->logger->info('No need to send the email message (Deleted Item or Event Date passed).');
                            }
                            $this->em->remove($item);
                            $this->em->flush();
                        }
                    }
// Temporarily disable sending of messages to NPs
$sendMessage = false;
                    if ($sendMessage) {
                        $name = $suitcase->getUser()->getFirstName() . ' ' .
                            $suitcase->getUser()->getLastName();
                        $email = $suitcase->getUser()->getEmail();
                        $account = $suitcase->getUser()->getCompany();
                        
                        $message = \Swift_Message::newInstance()
                            ->setSubject('Package No Longer Available')
                            ->setFrom(array('info@winspireme.com' => 'Winspire'))
                            ->setTo(array($email => $name))
                            ->setBody(
                                $this->templating->render(
                                    'InertiaWinspireBundle:Email:pulled-package-unpacked.html.twig',
                                    array(
                                        'suitcase' => $suitcase,
                                        'package' => $package
                                    )
                                ),
                                'text/html'
                            )
                            ->addPart(
                                $this->templating->render(
                                    'InertiaWinspireBundle:Email:pulled-package-unpacked.txt.twig',
                                    array(
                                        'suitcase' => $suitcase,
                                        'package' => $package
                                    )
                                ),
                                'text/plain'
                            )
                        ;
                        $message->setBcc(array($account->getSalesperson()->getEmail(), 'doug@inertiaim.com'));
                        
                        $this->mailer->send($message);
                    }
                }
                
                if ($suitcase->getStatus() == 'P') {
                    // Packed Suitcase
                    // No removal, just email the EC
                    // TODO Email template for EC notification?
                    $this->logger->info('Packed Suitcase contains inactive package: ' . $suitcase->getId());
                    $message = \Swift_Message::newInstance()
                        ->setSubject('Winspire::Package deactivated')
                        ->setFrom(array('info@winspireme.com' => 'Winspire'))
                        ->setTo(array('doug@inertiaim.com'))
                        ->setBody('Packed Suitcase is involved.' . "\n" .
                            'Suitcase ID: ' . $suitcase->getId() . "\n" .
                            'Package ID: ' . $package->getId() . "\n" .
                            'SF ID: ' . $package->getSfId() . "\n",
                        'text/plain')
                    ;
                    $this->mailer->send($message);
                }
            }
            
            return false;
        }
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
