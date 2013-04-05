<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Category;
use Inertia\WinspireBundle\Entity\Package;
use Inertia\WinspireBundle\Entity\User;


class SalesforceCommand extends ContainerAwareCommand
{
    private $pricebookId = '01s700000006IU7AAM';
    private $roleIds = '(\'00E700000018WJiEAM\', \'00E700000018HOeEAM\')';
    
    protected function configure()
    {
        $this->setName('sf:sync')
            ->setDescription('Salesforce manual sync')
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity to sync?');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = $input->getArgument('entity');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $client = $this->getContainer()->get('ddeboer_salesforce_client');
        
        switch(strtolower($entity)) {
            case 'categories':
                $output->writeln('<info>deleting local storage...</info>');
                
                // Delete all Packages
                $query = $em->createQuery(
                    'DELETE FROM InertiaWinspireBundle:Package p'
                );
                $query->getResult();
                
                // Delete all Categories
                $query = $em->createQuery(
                    'DELETE FROM InertiaWinspireBundle:Category c'
                );
                $query->getResult();
                
                // Raw database query to reset auto ids
                // This only works for MySQL
                $conn = $this->getContainer()->get('database_connection');
                $conn->query('ALTER TABLE package AUTO_INCREMENT=1');
                $conn->query('ALTER TABLE category AUTO_INCREMENT=1');
                
                
                $output->writeln('<info>retrieving SF objects...</info>');
                
                $categoryResult = $client->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'Parent_Category__c, ' .
                    'Child_Category__c, ' .
                    'WEB_category_sort__c, ' .
                    'WEB_category_longtail_slug__c ' .
                    'FROM Categories__c ' .
                    'WHERE ' .
                    'Parent_Category_Rank_del__c != 0 ' .
                    'AND Parent_Category__c != \'US Travel\' ' .
                    'ORDER BY WEB_category_sort__c, ' .
                    'Child_Category__c'
                );
                
                
                $output->writeln('<info>storing local cache...</info>');
                
                
                // Create the root category
                $root = new Category();
                $root->setTitle('All');
                $root->setNumber('');
                $root->setSfId('');
                $root->setSlug('all');
                $root->setOpen(1);
                $root->setCol(0);
                $em->persist($root);
                $em->flush();
                
                // Create the "US Travel" category
                // (which is handled differently than the other categories)
                $usTravel = new Category();
                $usTravel->setTitle('US Travel');
                $usTravel->setNumber('');
                $usTravel->setSfId('');
                $usTravel->setSlug('us-travel');
                $usTravel->setOpen(0);
                $usTravel->setCol(1);
                $usTravel->setParent($root);
                $em->persist($usTravel);
                $em->flush();
                
                
                $parentCategories = array();
                $childCategories = array();
                foreach($categoryResult as $c) {
                    if(!isset($parentCategories[$c->Parent_Category__c])) {
                        $parentCategories[$c->Parent_Category__c] = new Category();
                        $parentCategories[$c->Parent_Category__c]->setParent($root);
                        $parentCategories[$c->Parent_Category__c]->setTitle($c->Parent_Category__c);
                        $parentCategories[$c->Parent_Category__c]->setOpen(1);
                        $parentCategories[$c->Parent_Category__c]->setCol(floor(($c->WEB_category_sort__c / 100)));
                        $parentCategories[$c->Parent_Category__c]->setNumber('');
                        $parentCategories[$c->Parent_Category__c]->setSfId('');
                        $parentCategories[$c->Parent_Category__c]->setSlug($this->slugify($c->Parent_Category__c));
                        
                        
                        if($c->Parent_Category__c == $c->Child_Category__c) {
                            $parentCategories[$c->Parent_Category__c]->setNumber($c->Name);
                            $parentCategories[$c->Parent_Category__c]->setSfId($c->Id);
                            if(isset($c->WEB_category_longtail_slug__c)) {
                                $parentCategories[$c->Parent_Category__c]->setSlug($c->WEB_category_longtail_slug__c);
                            }
                            else {
                                $parentCategories[$c->Parent_Category__c]->setSlug($this->slugify($c->Parent_Category__c));
                            }
                            $em->persist($parentCategories[$c->Parent_Category__c]);
                            $em->flush();
                        }
                        else {
                            $em->persist($parentCategories[$c->Parent_Category__c]);
                            $em->flush();
                            
                            $category = new Category();
                            $category->setParent($parentCategories[$c->Parent_Category__c]);
                            $category->setTitle($c->Child_Category__c);
                            $category->setNumber($c->Name);
                            $category->setSfId($c->Id);
                            $category->setOpen(1);
                            $category->setCol(0);
                            
                            if(isset($c->WEB_category_longtail_slug__c)) {
                                $category->setSlug($c->WEB_category_longtail_slug__c);
                            }
                            else {
                                $category->setSlug($this->slugify($c->Child_Category__c));
                            }
                            
                            $em->persist($category);
                            $em->flush();
                        }
                        
//                        $em->persist($parentCategories[$c->Parent_Category__c]);
//                        $em->flush();
                    }
                    else {
                        $category = new Category();
                        $category->setParent($parentCategories[$c->Parent_Category__c]);
                        $category->setTitle($c->Child_Category__c);
                        $category->setNumber($c->Name);
                        $category->setSfId($c->Id);
                        $category->setOpen(1);
                        $category->setCol(floor(($c->WEB_category_sort__c / 100)));
                        
                        if(isset($c->WEB_category_longtail_slug__c)) {
                            $category->setSlug($c->WEB_category_longtail_slug__c);
                        }
                        else {
                            $category->setSlug($this->slugify($c->Child_Category__c));
                        }
                        
                        $em->persist($category);
                        $em->flush();
                    }
                }
                
                
                // go back and query for "US Travel" only
                $UsCategoryResult = $client->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'Parent_Category__c, ' .
                    'Child_Category__c, ' .
                    'WEB_category_sort__c, ' .
                    'WEB_category_longtail_slug__c ' .
                    'FROM Categories__c ' .
                    'WHERE ' .
                    'Parent_Category_Rank_del__c != 0 ' .
                    'AND Parent_Category__c = \'US Travel\' ' .
                    'ORDER BY WEB_category_sort__c NULLS LAST, ' .
                    'Child_Category__c'
                );
                
                foreach($UsCategoryResult as $c) {
                    $category = new Category();
                    $category->setParent($usTravel); 
                    $category->setTitle($c->Child_Category__c);
                    $category->setNumber($c->Name);
                    $category->setSfId($c->Id);
                    $category->setOpen(0);
                    $category->setCol(1);
                    
                    if(isset($c->WEB_category_longtail_slug__c)) {
                        $category->setSlug($c->WEB_category_longtail_slug__c);
                    }
                    else {
                        $category->setSlug($this->slugify($c->Child_Category__c));
                    }
                    
                    $em->persist($category);
                    $em->flush();
                }
                
            break;
            
            case 'packages':
                $packageResult = $client->query('SELECT ' .
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
                    'AND Parent_Header__c != \'\''
                );
                
                
                $count = 0;
                foreach ($packageResult as $p) {
                    // For now, only sync if there is a description available
                    if(isset($p->WEB_package_description__c) && $p->WEB_package_description__c != '') {
                        $output->writeln('<info>' . $p->Parent_Header__c . '</info>');
                        
                        $package = new Package();
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
                            $output->writeln('<error>No prices available...</error>');
                            continue;
                        }
                        
                        if($priceCounter > 1) {
                            $output->writeln('<error>Too many prices available...</error>');
                        }
                        
                        
                        
                        $em->persist($package);
                        $em->flush();
                        
                        $count++;
                        $output->writeln('<info>' . $count . '</info>');
                    }
                }
                break;
                
                
            case 'users':
                $userResult = $client->query('SELECT ' .
                    'u.Id, ' .
                    'u.Name, ' .
                    'u.Email, ' .
                    'u.Alias, ' .
                    'u.Phone, ' .
                    'u.UserRole.Id, ' .
                    'u.UserRole.Name ' .
                    'FROM User u ' .
                    'WHERE ' .
                    'u.UserRole.Id IN ' . $this->roleIds .
                    'AND IsActive = true '
                );
                
                $userManager = $this->getContainer()->get('fos_user.user_manager');
                
                foreach($userResult as $u) {
                    // Test whether the user already exists
                    $user = $em->getRepository('InertiaWinspireBundle:User')->findOneBySfId($u->Id);
                    
                    if(!$user) {
                        $user = $userManager->createUser();
                        $user->setType('S');
                        $user->setEnabled(true);
                        $user->addRole('ROLE_ADMIN');
                        $user->setPlainPassword('changeme');
                        $user->setNewsletter(true);
                        $user->setSfId($u->Id);
                    }
                    else {
                        $output->writeln('<error>User (' . $user->getEmail() . ') already in the system</error>');
                    }
                    
                    if($u->Id != '005700000013DkmAAE') {
                        $user->setUsername($u->Email);
                        $user->setEmail($u->Email);
                        $output->writeln('<info>User (' . $user->getEmail() . ')</info>');
                    }
                    
                    if(isset($u->Phone)) {
                        $user->setPhone($u->Phone);
                    }
                    
                    $name = explode(' ', $u->Name);
                    $user->setFirstName($name[0]);
                    $user->setLastName($name[1]);
                    
//                    try {
                        $userManager->updateUser($user);
//                    }
//                    catch(\Exception $e) {
//                        $output->writeln('<error>Ooops!</error>');
//                    }
                }
                
                break;
        }
    }
    
    protected function findCategoryByCode($code)
    {
echo '      Lookup: ' . $code . "\n";
        
        $em = $this->getContainer()->get('doctrine')->getManager();
        
        $query = $em->createQuery(
            'SELECT c FROM InertiaWinspireBundle:Category c WHERE c.number = :code'
        )
            ->setParameter('code', $code)
        ;
        
        try {
            $category = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
            echo 'problem with category lookup';
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
