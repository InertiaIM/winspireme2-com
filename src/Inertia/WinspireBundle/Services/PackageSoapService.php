<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Symfony\Bridge\Monolog\Logger;

class PackageSoapService
{
    protected $em;
    protected $sf;
    protected $logger;
    
    private $pricebookId = '01s700000006IU7AAM';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
    }
    
    public function notifications($notifications)
    {
$this->logger->info('It\'s big, it\'s heavy, it\'s wood');
        
        $dump = fopen('wtf.log', 'a');
        fwrite($dump, print_r($notifications, true));
        
        $id = $notifications->Notification->sObject->Id;
        
$this->logger->info('id: ' . $id);
        
//        $packages = $this->sf->retrieve(array(), array(), 'Product2');
        
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
            '(SELECT Id, Name, UnitPrice FROM PricebookEntries WHERE Pricebook2Id = \'' . $this->pricebookId . '\') ' .
            'FROM Product2 ' .
            'WHERE ' .
            'family = \'No-Risk Auction Package\' ' .
            'AND IsActive = true ' .
            'AND IsDeleted = false ' .
            'AND WEB_package_subtitle__c != \'\' ' .
            'AND Parent_Header__c != \'\' ' .
            'AND Id = \''. $id . '\''
        );
        
        
        if(count($packageResult) == 0) {
$this->logger->info('Package doesn\'t meet the criteria');
            return array('Ack' => true);
        }
        
        
        
        
        
        // Test whether this package is already in our database
        $package = $this->em->getRepository('InertiaWinspireBundle:Package')->findOneBySfId($id);
        
        if(!$package) {
            // New package, not in our database yet
$this->logger->info('New package to be added');
            $package = new Package();
        }
        else {
            // Package already exists, just update
$this->logger->info('Existing package to be updated');
            
        }
        
        
        
        
        
        foreach ($packageResult as $p) {
            // For now, only sync if there is a description available
            if(isset($p->WEB_package_description__c) && $p->WEB_package_description__c != '') {
                $package->setName($p->WEB_package_subtitle__c);
                $package->setParentHeader($p->Parent_Header__c);
                $package->setCode($p->ProductCode);
                $package->setSfId($p->Id);
                $package->setIsOnHome($p->Home_Page_view__c);
                $package->setIsBestSeller($p->Best_Seller__c);
                $package->setIsNew($p->New_Item__c);
                $package->setSeasonal($p->WEB_seasonal_pkg__c);
                $package->setIsDefault($p->WEB_Default_version__c);
                $package->setSuggestedRetailValue($p->Suggested_Retail_Value__c);
                $package->setYearVersion($p->Year_Version__c);
                
                if(isset($p->OMIT_from_Winspire__c)) {
                    $package->setIsPrivate($p->OMIT_from_Winspire__c == '1' ? true : false);
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
                
                if(isset($p->WEB_Nights__c)) {
                    $package->setAccommodations($p->WEB_Nights__c);
                }
                
                if(isset($p->WEB_Participants__c)) {
                    $package->setPersons($p->WEB_Participants__c);
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
                    
                    $package->setKeywords(serialize($keywords));
                }
                
                if(isset($p->WEB_Recommendations__c)) {
                    $recommendations = explode(';', $p->WEB_Recommendations__c);
                    
                    foreach($recommendations as $i => $r) {
                        $recommendations[$i] = trim($r);
                    }
                    
                    $package->setRecommendations(serialize($recommendations));
                }
                
                
                
                $categories = array();
                if(isset($p->Package_Category_Pairings__c)) {
                    $categories = explode(';', $p->Package_Category_Pairings__c);
                }
                
                foreach($categories as $category) {
                    $package->addCategory($this->findCategoryByCode(trim($category)));
                }
                
                
                
                
                
                
                
                $priceCounter = 0;
                if(isset($p->PricebookEntries)) {
                    foreach ($p->PricebookEntries as $price) {
                        $package->setCost($price->UnitPrice);
                        $package->setSfPricebookEntryId($price->Id);
                        $priceCounter++;
                    }
                }
                else {
//                    $output->writeln('<error>No prices available...</error>');
                    continue;
                }
                
                if($priceCounter > 1) {
//                    $output->writeln('<error>Too many prices available...</error>');
                }
                
                
                
                $this->em->persist($package);
                $this->em->flush();
                
                
$this->logger->info('Package saved...');
            }
        }
        
        
        
        
        
        
        
        
        return array('Ack' => true);
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
        }
        
        return $category;
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