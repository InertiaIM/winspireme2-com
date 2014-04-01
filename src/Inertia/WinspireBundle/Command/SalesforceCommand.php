<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Account;
use Inertia\WinspireBundle\Entity\Category;
use Inertia\WinspireBundle\Entity\Package;
use Inertia\WinspireBundle\Entity\PackageOrigin;
use Inertia\WinspireBundle\Entity\User;


class SalesforceCommand extends ContainerAwareCommand
{
    private $recordTypeId = '01270000000DVD5AAO';
    private $pricebookId = '01s700000006IU7AAM';
    private $roleIds = '(\'00E700000018WJiEAM\', \'00E700000018HOeEAM\')';
    private $opportunityTypeId = '01270000000DVGnAAO';
    private $partnerRecordId = '0017000000PKyUfAAL';
    
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
                    'IsActive, ' .
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
                    'Origin__c, ' .
                    'SystemModstamp ' .
                    'FROM Product2 ' .
                    'WHERE ' .
                    'family = \'No-Risk Auction Package\' ' .
                    'AND IsDeleted = false ' .
                    'AND WEB_package_subtitle__c != \'\' ' .
                    'AND Parent_Header__c != \'\''
                );
                
                
                $count = 0;
                foreach ($packageResult as $p) {
                    $output->writeln('<info>' . $p->Parent_Header__c . '</info>');
                    
                    $package = $em->getRepository('InertiaWinspireBundle:Package')->findOneBySfId($p->Id);
                    
                    if(!$package) {
                        // New package, not in our database yet
                        $output->writeln('<info>New package (' . $p->Id . ') to be added</info>');
                        
                        // If new package and inactive, there's no need to add it
                        if ($p->IsActive != '1') {
                            continue;
                        }
                        
                        $package = new Package();
                    }
                    else {
                        // Package already exists, just update
                        $output->writeln('<info>Existing package (' . $p->Id . ') to be updated</info>');
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
                    
                    // TODO IF CHANGE FROM Active -> Inactive???
                    if(isset($p->IsActive)) {
                        $package->setActive($p->IsActive == '1' ? true : false);
                        if (!$package->getActive()) {
                            $output->writeln('<error>INACTIVE package (' . $p->Id . ')</error>');
                        }
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
                    
                    $origins = array();
                    if(isset($p->Origin__c)) {
                        $origins = explode(';', $p->Origin__c);
                    }
                    foreach($package->getOrigins() as $o) {
                        $package->removeOrigin($o);
                    }
                    foreach($origins as $origin) {
                        $o = new PackageOrigin();
                        $o->setCode($origin);
                        $package->addOrigin($o);
                    }
                    
                    $output->writeln('<info>Starting pricebook lookup...</info>');
                    
                    $pricebookResult = $client->query('SELECT ' .
                        'Id, ' .
                        'Name, ' .
                        'UnitPrice ' .
                        'FROM PricebookEntry ' .
                        'WHERE Pricebook2Id = \'' . $this->pricebookId . '\' ' .
                        'AND Product2Id = \'' . $p->Id . '\''
                    );
                    
                    if(count($pricebookResult) < 1) {
                        $output->writeln('<error>No prices available, so we\'re not saving this Package</error>');
                        continue;
                    }
                    
                    $pricebookEntry = $pricebookResult->first();
                    $package->setSfPricebookEntryId($pricebookEntry->Id);
                    $package->setCost($pricebookEntry->UnitPrice);
                    $package->setUpdated($p->SystemModstamp);
                    
                    
                    
                    $em->persist($package);
                    $em->flush();
                    
                    $count++;
                    $output->writeln('<info>' . $count . '</info>');
                    $output->writeln('<info>Package saved...</info>');
                }
                
                $output->writeln('<info>Now we\'ll generate our search index...</info>');
                $indexer = $this->getContainer()->get('search.sphinxsearch.indexer');
                $indexer->rotate('Packages');
                
                break;


            case 'dump':
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
                    'IsActive, ' .
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
                    'Origin__c, ' .
                    'SystemModstamp ' .
                    'FROM Product2 ' .
                    'WHERE ' .
                    'family = \'No-Risk Auction Package\' ' .
                    'AND IsDeleted = false ' .
                    'AND WEB_package_subtitle__c != \'\' ' .
                    'AND Parent_Header__c != \'\''
                );

$output->writeln('<info>PACKAGE COUNT: ' . count($packageResult) . '</info>');
                $count = 0;
                foreach ($packageResult as $p) {
                    $output->writeln('<info>' . $p->Parent_Header__c . '</info>');
                    
                    $package = $em->getRepository('InertiaWinspireBundle:Package')->findOneBySfId($p->Id);
                    
                    if(!$package) {
                        continue;
                    }
                    else {
                        // Package already exists, just update
                        $output->writeln('<info>Existing package (' . $p->Id . ') to be updated</info>');
                    }
                    
                    $origins = array();
                    if(isset($p->Origin__c)) {
                        $origins = explode(';', $p->Origin__c);
                    }
                    foreach($package->getOrigins() as $o) {
                        $package->removeOrigin($o);
                        $em->remove($o);
                        $em->flush();
$output->writeln('<error>Remove origin (' . $o->getCode() . ') </error>');
                    }
                    foreach($origins as $origin) {
                        switch (strtolower($origin)) {
                            case 'canada':
                                $locale = 'ca';
                                break;
                            case 'us':
                                $locale = 'us';
                                break;
                            default:
                                $locale = strtolower($origin);
                        }
                        $o = new PackageOrigin();
                        $o->setCode($locale);
                        $em->persist($o);
                        $package->addOrigin($o);
                    }
                    
                    $package->setUpdated($p->SystemModstamp);
                    
                    $em->persist($package);
                    $em->flush();

                    $count++;
                    $output->writeln('<info>' . $count . '</info>');
                    $output->writeln('<info>Package saved...</info>');
                }
                
//                $output->writeln('<info>Now we\'ll generate our search index...</info>');
//                $indexer = $this->getContainer()->get('search.sphinxsearch.indexer');
//                $indexer->rotate('Packages');

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
                    $user = $userManager->findUserBy(array('sfId' => $u->Id));
                    
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
                        $output->writeln('<info>User (' . $user->getEmail() . ') already in the system</info>');
                    }
                    
                    if($u->Id != '005700000013DkmAAE') {
                        $user->setUsername($u->Email);
                        $user->setEmail($u->Email);
                    }
                    
                    if(isset($u->Phone)) {
                        $user->setPhone($u->Phone);
                    }
                    
                    $name = explode(' ', $u->Name);
                    $user->setFirstName($name[0]);
                    $user->setLastName($name[1]);
                    
                    try {
                        $userManager->updateUser($user);
                    }
                    catch(\Exception $e) {
                        $output->writeln('<error>Ooops!</error>');
                    }
                }
                
                break;
                
            case 'accounts':
                // Phase 1:  Push all "dirty" records in our Account table
                $query = $em->createQuery(
                    'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.dirty = 1'
                );
                $accounts = $query->getResult();
                
                foreach ($accounts as $account) {
                    $account->setNameCanonical($this->slugify($account->getName()));
                    
                    if ($account->getSfId() == '') {
                        $new = true;
                    }
                    else {
                        $new = false;
                    }
                    
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
                    $sfAccount->Referred_by__c = $account->getReferred();
                    $sfAccount->RecordTypeId = $this->recordTypeId;
                    $sfAccount->OwnerId = $account->getSalesperson()->getSfId();
                    
                    if ($new) {
                        $saveResult = $client->create(array($sfAccount), 'Account');
                    }
                    else {
                        $sfAccount->Id = $account->getSfId();
                        $saveResult = $client->update(array($sfAccount), 'Account');
                    }
                    
                    if($saveResult[0]->success) {
                        $timestamp = new \DateTime();
                        $account->setSfId($saveResult[0]->id);
                        $account->setDirty(false);
                        $account->setSfUpdated($timestamp);
                        $account->setUpdated($timestamp);
                        $em->persist($account);
                        $em->flush();
                    }
                }
                
                
                
                // Phase 2:  Attempt to locate and remove deleted Accounts
                $query = $em->createQuery(
                    'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId IS NOT NULL AND a.sfId NOT IN (:blah)'
                );
                $query->setParameter('blah', array('TEST', 'PARTNER'));
                $accounts = $query->getResult();
                $count = 0;
                $ids = array();
                foreach ($accounts as $account) {
                    $count++;
                    $ids[] = $account->getSfId();
                    if ($count == 2000) {
                        $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                        
                        $result = $client->retrieve(array('Id'), $ids, 'Account');
                        foreach ($result as $key => $value) {
                            if ($value === null && ($accounts[$key]->getSfId() == $ids[$key])) {
                                if (count($accounts[$key]->getUsers()) == 0) {
                                    $output->writeln('<info>Gonna delete: ' . $ids[$key] . '</info>');
                                    $em->remove($accounts[$key]);
                                    $em->flush();
                                }
                                else {
                                    $output->writeln('<error>Can\'t delete: ' . $ids[$key] . '</error>');
                                }
                            }
                        }
                        
                        $count = 0;
                        $ids = array();
                    }
                }
                
                $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                $result = $client->retrieve(array('Id'), $ids, 'Account');
                foreach ($result as $key => $value) {
                    if ($value === null && ($accounts[$key]->getSfId() == $ids[$key])) {
                        if (count($accounts[$key]->getUsers()) == 0) {
                            $output->writeln('<info>Gonna delete: ' . $ids[$key] . '</info>');
                            $em->remove($accounts[$key]);
                            $em->flush();
                        }
                        else {
                            $output->writeln('<error>Can\'t delete: ' . $ids[$key] . '</error>');
                        }
                    }
                }
                
                
                
                // Phase 3:  Check for viable Account changes and new Accounts from SF
                $accountResult = $client->query('SELECT ' .
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
                    'SystemModstamp ' .
                    'FROM Account ' .
                    'WHERE ' .
                    'RecordTypeId = \'' . $this->recordTypeId . '\''
                );
                
                // If we don't receive any Accounts, then it doesn't meet the criteria
                if(count($accountResult) == 0) {
                    $output->writeln('<error>Ooops, no accounts found!</error>');
                }
                
                foreach ($accountResult as $sfAccount) {
                    // Test whether this account is already in our database
                    $account = $em->getRepository('InertiaWinspireBundle:Account')->findOneBySfId($sfAccount->Id);
                    
                    if(!$account) {
                        // New account, not in our database yet
//                        $output->writeln('<info>New account (' . $sfAccount->Id . ') to be added</info>');
                        $account = new Account();
                        $new = true;
                    }
                    else {
                        // Account already exists, just an update
//                        $output->writeln('<info>Existing account (' . $sfAccount->Id . ') to be updated (maybe)</info>');
                        $new = false;
                    }
                    
                    
                    if ($new || (($sfAccount->SystemModstamp > $account->getSfUpdated()) && !$account->getDirty())) {
                        
if($new) {
    $output->writeln('<info>NEW RECORD: ' . $sfAccount->Id . '</info>');
}
if(($sfAccount->SystemModstamp > $account->getSfUpdated()) && !$account->getDirty()) {
    $output->writeln('<info>EXISTING RECORD: ' . $sfAccount->Id . '</info>');
    $output->writeln($sfAccount->SystemModstamp);
}
                        
                        // ACCOUNT NAME
                        if(isset($sfAccount->Name)) {
                            $account->setName($sfAccount->Name);
                        }
                        $account->setNameCanonical($this->slugify($account->getName()));
                        
                        // ACCOUNT ADDRESS
                        if(isset($sfAccount->BillingStreet)) {
                            $address = explode(chr(10), $sfAccount->BillingStreet);
                            $account->setAddress(trim($address[0]));
                            if(isset($address[1])) {
                                $account->setAddress2(trim($address[1]));
                            }
                        }
                        
                        // ACCOUNT CITY
                        if(isset($sfAccount->BillingCity)) {
                            $account->setCity($sfAccount->BillingCity);
                        }
                        
                        // ACCOUNT STATE
                        if(isset($sfAccount->BillingState)) {
                            // TODO Need to test for proper two-letter state code
                            $account->setState($sfAccount->BillingState);
                        }
                        
                        // ACCOUNT ZIP
                        if(isset($sfAccount->BillingPostalCode)) {
                            $account->setZip($sfAccount->BillingPostalCode);
                        }
                        
                        // ACCOUNT ZIP
                        if(isset($sfAccount->BillingCountry)) {
                            if (strtoupper($sfAccount->BillingCountry) == 'CANADA' ||
                                strtoupper($sfAccount->BillingCountry) == 'CA') {
                                $account->setCountry('CA');
                            }
                            else {
                                $account->setCountry('US');
                            }
                        }
                        
                        // ACCOUNT PHONE
                        if(isset($sfAccount->Phone)) {
                            $account->setPhone($sfAccount->Phone);
                        }
                        
                        // ACCOUNT REFERRED
                        if(isset($sfAccount->Referred_by__c)) {
                            $account->setReferred($sfAccount->Referred_by__c);
                        }
                        
                        // ACCOUNT OWNER
                        if(isset($sfAccount->OwnerId)) {
                            $query = $em->createQuery(
                                'SELECT u FROM InertiaWinspireBundle:User u WHERE u.sfId = :sfid'
                            )
                                ->setParameter('sfid', $sfAccount->OwnerId)
                            ;
                            
                            try {
                                $owner = $query->getSingleResult();
                                $output->writeln('<info>    Owner: ' .  $owner->getEmail() . '</info>');
                                $account->setSalesperson($owner);
                            }
                            catch (\Exception $e) {
                                $output->writeln('<error>    Owner ID es no bueno: ' . $sfAccount->OwnerId . '</error>');
                                $query = $em->createQuery(
                                    'SELECT u FROM InertiaWinspireBundle:User u WHERE u.id = :id'
                                )
                                    ->setParameter('id', 1)
                                ;
                                $defaultOwner = $query->getSingleResult();
                                
                                $account->setSalesperson($defaultOwner);
                            }
                        }
                        else {
                            $output->writeln('<error>    Missing OwnerId?!?!</error>');
                        }
                        
                        
                        $account->setSfId($sfAccount->Id);
                        
                        $timestamp = new \DateTime();
                        $account->setSfUpdated($timestamp);
                        $account->setUpdated($timestamp);
                        
                        $em->persist($account);
                        $em->flush();
                        $em->clear();
                        
                        $output->writeln('<info>    Account saved...</info>');
                    }
                    
//$output->writeln('<info>NO UPDATE: ' . $sfAccount->Id . '</info>');
                    
                    
                }
                
                break;
                
                
            case 'contacts':
                // Phase 1:  Push all "dirty" records in our Contact table
                $output->writeln('<info>Phase 1: Push all "dirty" records in our Contact table</info>');
                $query = $em->createQuery(
                    'SELECT u, a FROM InertiaWinspireBundle:User u JOIN u.company a WITH a.sfId IS NOT NULL AND a.sfId NOT IN (:blah) WHERE u.dirty = 1'
                );
                $query->setParameter('blah', array('TEST', 'PARTNER'));
                $contacts = $query->getResult();
                
                foreach ($contacts as $contact) {
                    if ($contact->getSfId() == '') {
                        $new = true;
                    }
                    else {
                        $new = false;
                    }
                    
                    $sfContact = new \stdClass();
                    $sfContact->FirstName = $contact->getFirstName();
                    $sfContact->LastName = $contact->getLastName();
                    $sfContact->Phone = $contact->getPhone();
                    $sfContact->Email = $contact->getEmailCanonical();
                    $sfContact->AccountId = $contact->getCompany()->getSfId();
                    $sfContact->OwnerId = $contact->getCompany()->getSalesperson()->getSfId();
                    $sfContact->Default_contact__c = 1;
                    
                    $output->writeln('<info>Pushing ' . $contact->getSfId() == '' ? $contact->getId() : $contact->getSfId() . ' to SF</info>');
                    
                    if ($new) {
                        $saveResult = $client->create(array($sfContact), 'Contact');
                    }
                    else {
                        $sfContact->Id = $contact->getSfId();
                        $saveResult = $client->update(array($sfContact), 'Contact');
                    }
                    
                    if($saveResult[0]->success) {
                        $timestamp = new \DateTime();
                        $contact->setSfId($saveResult[0]->id);
                        $contact->setDirty(false);
                        $contact->setSfUpdated($timestamp);
                        $contact->setUpdated($timestamp);
                        $em->persist($contact);
                        $em->flush();
                    }
                }
                
                
                
                // Phase 2:  Attempt to locate deleted (and changed) Contacts
                $output->writeln('<info>Phase 2: Attempt to locate deleted (and changed) Contacts</info>');
                $query = $em->createQuery(
                    'SELECT u FROM InertiaWinspireBundle:User u WHERE u.sfId IS NOT NULL AND u.sfId NOT IN (:blah) AND u.type = \'C\''
                );
                $query->setParameter('blah', array('TEST', 'PARTNER'));
                $contacts = $query->getResult();
                $count = 0;
                $ids = array();
                foreach ($contacts as $contact) {
                    $count++;
                    $ids[] = $contact->getSfId();
                    if ($count == 2000) {
                        $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                        
                        $result = $client->retrieve(array('Id', 'FirstName', 'LastName', 'Phone', 'Email', 'AccountId', 'SystemModstamp'), $ids, 'Contact');
                        foreach ($result as $key => $value) {
                            if ($value === null && ($contacts[$key]->getSfId() == $ids[$key])) {
                                $output->writeln('<error>Missing Contact on SF: ' . $ids[$key] . '</error>');
                            }
                            else {
                                if (($contacts[$key]->getSfId() == $ids[$key]) && ($value->SystemModstamp > $contacts[$key]->getSfUpdated())) {
                                    $output->writeln('<info>Updating Contact: ' . $ids[$key] . '</info>');
                                    $output->writeln('<info>Updating Contact: ' . $value->FirstName . '</info>');
                                    $output->writeln('<info>Updating Contact: ' . $value->LastName . '</info>');
                                    if (isset($value->Email)) {
                                        $output->writeln('<info>Updating Contact: ' . $value->Email . '</info>');
                                    }
                                    if (isset($value->Phone)) {
                                        $output->writeln('<info>Updating Contact: ' . $value->Phone . '</info>');
                                    }
                                    
                                    $contacts[$key]->setFirstName($value->FirstName);
                                    $contacts[$key]->setLastName($value->LastName);
                                    if (isset($value->Email)) {
                                        $contacts[$key]->setEmail($value->Email);
                                    }
                                    if (isset($value->Phone)) {
                                        $contacts[$key]->setPhone($value->Phone);
                                    }
                                    // TODO Assign Account
                                    
                                    $timestamp = new \DateTime();
                                    $contacts[$key]->setDirty(false);
                                    $contacts[$key]->setSfUpdated($timestamp);
                                    $contacts[$key]->setUpdated($timestamp);
                                    $em->persist($contacts[$key]);
                                    $em->flush();
                                }
                            }
                        }
                        
                        $count = 0;
                        $ids = array();
                    }
                }
                
                $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                $result = $client->retrieve(array('Id', 'FirstName', 'LastName', 'Phone', 'Email', 'AccountId', 'SystemModstamp'), $ids, 'Contact');
                foreach ($result as $key => $value) {
                    if ($value === null && ($contacts[$key]->getSfId() == $ids[$key])) {
                        $output->writeln('<error>Missing Contact on SF: ' . $ids[$key] . '</error>');
                    }
                    else {
                        if (($contacts[$key]->getSfId() == $ids[$key]) && ($value->SystemModstamp > $contacts[$key]->getSfUpdated())) {
                            $output->writeln('<info>Updating Contact: ' . $ids[$key] . '</info>');
                            $output->writeln('<info>Updating Contact: ' . $value->FirstName . '</info>');
                            $output->writeln('<info>Updating Contact: ' . $value->LastName . '</info>');
                            if (isset($value->Email)) {
                                $output->writeln('<info>Updating Contact: ' . $value->Email . '</info>');
                            }
                            if (isset($value->Phone)) {
                                $output->writeln('<info>Updating Contact: ' . $value->Phone . '</info>');
                            }
                            
                            $contacts[$key]->setFirstName($value->FirstName);
                            $contacts[$key]->setLastName($value->LastName);
                            if (isset($value->Email)) {
                                $contacts[$key]->setEmail($value->Email);
                            }
                            if (isset($value->Phone)) {
                                $contacts[$key]->setPhone($value->Phone);
                            }
                            //TODO Assign Account
                            
                            $timestamp = new \DateTime();
                            $contacts[$key]->setSfId($value->Id);
                            $contacts[$key]->setDirty(false);
                            $contacts[$key]->setSfUpdated($timestamp);
                            $contacts[$key]->setUpdated($timestamp);
                            $em->persist($contacts[$key]);
                            $em->flush();
                        }
                    }
                }
                
                break;
                
                
                
            case 'suitcases':
                // Phase 1:  Push all "dirty" records in our Suitcase table
                $query = $em->createQuery(
                    'SELECT a, u, s FROM InertiaWinspireBundle:Suitcase s JOIN s.user u WITH u.sfId IS NOT NULL JOIN u.company a WITH a.sfId NOT IN (:blah) WHERE s.dirty = 1'
                );
                $query->setParameter('blah', array('TEST', 'PARTNER'));
                $suitcases = $query->getResult();
                
                foreach ($suitcases as $suitcase) {
                    $sfOpportunity = new \stdClass();
                    
                    if ($suitcase->getSfId() == '') {
                        $sfOpportunity->StageName = 'Counsel';
                        $new = true;
                    }
                    else {
                        $new = false;
                    }
                    
                    if ($suitcase->getStatus() != 'U') {
                        $sfOpportunity->Website_suitcase_status__c = 'Packed';
                    }
                    else {
                        $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
                    }
                    
                    $sfOpportunity->Name = $suitcase->getName();
                    
//                    if ($suitcase->getEventName() != '' && $suitcase->getEventName() != 'false') {
//                        $sfOpportunity->Event_Name__c = substr($suitcase->getEventName(), 0, 40);
//                    }
//                    else {
//                        $sfOpportunity->Event_Name__c = '';
//                    }
                    if ($suitcase->getEventDate() != '') {
                        $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
                    }
                    else {
                        $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
                    }
                    
                    if ($suitcase->getInvoiceRequestedAt() != '' && $suitcase->getStatus() != 'U' && $suitcase->getStatus() != 'P') {
                        $sfOpportunity->CloseDate = $suitcase->getInvoiceRequestedAt();
                    }
                    else {
                        // TODO what to user here?
//                        $sfOpportunity->CloseDate = new \DateTime('+60 days');
                    }
                    
                    $sfOpportunity->AccountId = $suitcase->getUser()->getCompany()->getSfId();
                    $sfOpportunity->OwnerId = $suitcase->getUser()->getCompany()->getSalesperson()->getSfId();
                    
                    if ($new) {
                        $output->writeln('<info>Gonna create: ' . $suitcase->getId() . '</info>');
                        $sfOpportunity->Type = 'Web Suitcase';
                        $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
                        $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
                        $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
                        $sfOpportunity->Item_Use__c = 'Silent Auction';
                        $saveResult = $client->create(array($sfOpportunity), 'Opportunity');
                    }
                    else {
                        $sfOpportunity->Id = $suitcase->getSfId();
                        $output->writeln('<info>Gonna update: ' . $suitcase->getSfId() . '</info>');
                        $saveResult = $client->update(array($sfOpportunity), 'Opportunity');
                    }
                    
                    if($saveResult[0]->success) {
                        $suitcase->setSfId($saveResult[0]->id);
//                        $timestamp = new \DateTime();
                        $suitcase->setDirty(false);
//                        $suitcase->setSfUpdated($timestamp);
//                        $suitcase->setUpdated($timestamp);
                        $em->persist($suitcase);
                        $em->flush();
                    }
                }
                
                
/*                
                // Phase 2:  Attempt to locate deleted (and changed) Opportunities
                $query = $em->createQuery(
                    'SELECT a, u, s FROM InertiaWinspireBundle:Suitcase s JOIN s.user u WITH u.sfId IS NOT NULL JOIN u.company a WITH a.sfId NOT IN (:blah) AND a.sfId IS NOT NULL WHERE s.dirty = 0'
                );
                $query->setParameter('blah', array('TEST', 'PARTNER'));
                $suitcases = $query->getResult();
                $count = 0;
                $ids = array();
                foreach ($suitcases as $suitcase) {
                    $count++;
                    $ids[] = $suitcase->getSfId();
                    if ($count == 2000) {
                        $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                        
                        $result = $client->retrieve(array('Id', 'Name', 'Event_Name__c', 'Event_Date__c', 'AccountId', 'SystemModstamp'), $ids, 'Opportunity');
                        foreach ($result as $key => $value) {
                            if ($value === null && ($suitcases[$key]->getSfId() == $ids[$key])) {
                                $output->writeln('<error>Missing Opportunity in SF: ' . $ids[$key] . '</error>');
                            }
                            else {
                                if (($suitcases[$key]->getSfId() == $ids[$key]) && ($value->SystemModstamp > $suitcases[$key]->getSfUpdated())) {
                                    $output->writeln('<info>Updating Suitcase: ' . $ids[$key] . '</info>');
                                    $output->writeln('<info>    Name: ' . $value->Name . '</info>');
                                    $suitcases[$key]->setName($value->Name);
                                    
                                    if(isset($value->Event_Name__c)) {
                                        $output->writeln('<info>    Event Name: ' . $value->Event_Name__c . '</info>');
                                        $suitcases[$key]->setEventName($value->Event_Name__c);
                                    }
                                    if(isset($value->Event_Date__c)) {
                                        $output->writeln('<info>    Event Date: ' . $value->Event_Date__c->format('Ymd') . '</info>');
                                        $suitcases[$key]->setEventDate($value->Event_Date__c);
                                    }
                                    
                                    
                                    // TODO Assign Account
                                    
                                    $timestamp = new \DateTime();
                                    $suitcases[$key]->setSfUpdated($timestamp);
                                    $suitcases[$key]->setUpdated($timestamp);
                                    $em->persist($suitcases[$key]);
                                    $em->flush();
                                }
                            }
                        }
                        
                        $count = 0;
                        $ids = array();
                    }
                }
                
                $output->writeln('<info>Gonna retrieve now...' . $count . '</info>');
                $result = $client->retrieve(array('Id', 'Name', 'Event_Name__c', 'Event_Date__c', 'AccountId', 'SystemModstamp'), $ids, 'Opportunity');
                foreach ($result as $key => $value) {
                    if ($value === null && ($suitcases[$key]->getSfId() == $ids[$key])) {
                        $output->writeln('<error>Missing Opportunity in SF: ' . $ids[$key] . '</error>');
                    }
                    else {
                        if (($suitcases[$key]->getSfId() == $ids[$key]) && ($value->SystemModstamp > $suitcases[$key]->getSfUpdated())) {
                            $output->writeln('<info>Updating Suitcase: ' . $ids[$key] . '</info>');
                            $output->writeln('<info>    Name: ' . $value->Name . '</info>');
                            $suitcases[$key]->setName($value->Name);
                            
                            if(isset($value->Event_Name__c)) {
                                $output->writeln('<info>    Event Name: ' . $value->Event_Name__c . '</info>');
                                $suitcases[$key]->setEventName($value->Event_Name__c);
                            }
                            if(isset($value->Event_Date__c)) {
                                $output->writeln('<info>    Event Date: ' . $value->Event_Date__c->format('Ymd') . '</info>');
                                $suitcases[$key]->setEventDate($value->Event_Date__c);
                            }
                            
                            
                            // TODO Assign Account
                            
                            $timestamp = new \DateTime();
                            $suitcases[$key]->setSfUpdated($timestamp);
                            $suitcases[$key]->setUpdated($timestamp);
                            $em->persist($suitcases[$key]);
                            $em->flush();
                        }
                    }
                }
*/
                break;
                
            case 'suitcase-items':
                $query = $em->createQuery(
                    'SELECT s, i FROM InertiaWinspireBundle:Suitcase s LEFT JOIN s.items i WHERE s.status = \'U\' ORDER BY s.id'
                );
                $suitcases = $query->getResult();
                
                foreach ($suitcases as $suitcase) {
                    // Let's just make sure the Suitcase has already been added to SF
                    if ($suitcase->getSfId() == '') {
                        $sfOpportunity = new \stdClass();
                        $sfOpportunity->Name = substr($suitcase->getEventName(), 0, 40);
                        $sfOpportunity->Website_suitcase_status__c = 'Unpacked';
                        $sfOpportunity->StageName = 'Counsel';
                        if ($suitcase->getEventDate() != '') {
                            $sfOpportunity->Event_Date__c = $suitcase->getEventDate();
                            $sfOpportunity->CloseDate = new \DateTime($suitcase->getEventDate()->format('Y-m-d') . '+30 days');
                        }
                        else {
                            $sfOpportunity->Event_Date__c = new \DateTime('+30 days');
                            $sfOpportunity->CloseDate = new \DateTime('+60 days');
                        }
                        $sfOpportunity->AccountId = $suitcase->getUser()->getCompany()->getSfId();
                        $sfOpportunity->RecordTypeId = $this->opportunityTypeId;
                        $sfOpportunity->Lead_Souce_by_Client__c = 'Online User';
                        $sfOpportunity->Partner_Class__c = $this->partnerRecordId;
                        $sfOpportunity->Item_Use__c = 'Silent Auction';
                        $sfOpportunity->Type = 'Web Suitcase';
                        
                        $output->writeln('<info>Creating Opportunity in SF: ' . $suitcase->getId() . '</info>');
                        $saveResult = $client->create(array($sfOpportunity), 'Opportunity');
                        
                        if($saveResult[0]->success) {
                            $timestamp = new \DateTime();
                            $suitcase->setSfId($saveResult[0]->id);
                            $suitcase->setDirty(false);
                            $suitcase->setSfUpdated($timestamp);
                            $suitcase->setUpdated($timestamp);
                            $em->persist($suitcase);
                            $em->flush();
                        }
                    }
                    
                    $sfOpportunityLineItems = array();
                    $newItems = array();
                    foreach ($suitcase->getItems() as $item) {
                        // Item has been deleted
                        if ($item->getStatus() == 'X') {
                            // Item is already in SF; so we need to delete it
                            if ($item->getSfId() != '') {
                                $output->writeln('<info>Deleting Suitcase Item from SF: ' . $item->getSfId() . '</info>');
                                
                                try {
                                    $deleteResult = $client->delete(array($item->getSfId()));
                                    if ($deleteResult[0]->success) {
                                        $em->remove($item);
                                    }
                                }
                                catch (\Exception $e) {
                                    $output->writeln('<error>Problem deleting Suitcase Item: ' . $item->getSfId() . '</error>');
                                }
                            }
                            else {
                                $em->remove($item);
                            }
                            $em->flush();
                            
                            continue;
                        }
                        
                        // Has the item already been sync'd
                        if ($item->getSfId() != '') {
                            continue;
                        }
                        
                        $sfOpportunityLineItem = new \stdClass();
                        $sfOpportunityLineItem->Quantity = 1;
                        $sfOpportunityLineItem->UnitPrice = $item->getPackage()->getCost();
                        $sfOpportunityLineItem->Package_Status__c = ($suitcase->getStatus() == 'P') ? 'Reserved' : 'Interested';
                        $sfOpportunityLineItem->OpportunityId = $suitcase->getSfId();
                        $sfOpportunityLineItem->PricebookEntryId = $item->getPackage()->getSfPricebookEntryId();
                        $sfOpportunityLineItems[] = $sfOpportunityLineItem;
                        $newItems[] = $item;
                    }
                    
                    if (count($sfOpportunityLineItems)) {
                        $output->writeln('<info>Creating Suitcase Items in SF: ' . $suitcase->getSfId() . '</info>');
                        foreach ($newItems as $i) {
                            $output->writeln('<info>    ' . $i->getId() . '</info>');
                        }
                        
                        try {
                            $saveResult = $client->create($sfOpportunityLineItems, 'OpportunityLineItem');
                            
                            foreach ($saveResult as $index => $result) {
                                if($result->success) {
                                    $newItems[$index]->setSfId($result->id);
                                    $em->persist($newItems[$index]);
                                }
                            }
                            
                            $em->flush();
                        }
                        catch (\Exception $e) {
                            $output->writeln('<error>Problem creating Suitcase Items: ' . $suitcase->getSfId() . '</error>');
                        }
                    }
                }
                
                break;
        }
    }
    
    
    protected function findPackageByCode($code)
    {
echo '      Lookup package: ' . $code . "\n";
        $em = $this->getContainer()->get('doctrine')->getManager();
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.code = :code AND p.active = 1 ORDER BY p.created DESC'
        )
            ->setParameter('code', $code)
            ->setMaxResults(1);
        ;
        
        try {
            $package = $query->getSingleResult();
        }
        catch (\Doctrine\Orm\NoResultException $e) {
echo 'problem with package lookup' . "\n";
            $package = false;
        }
        
        return $package;
    }
    
    protected function findCategoryByCode($code)
    {
echo '      Lookup category: ' . $code . "\n";
        
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
echo 'problem with category lookup' . "\n";
            $category = false;
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
