<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Category;
use Inertia\WinspireBundle\Entity\Package;

class SalesforceCommand extends ContainerAwareCommand
{
    private $pricebookId = '01s700000006IU7AAM';
    
    protected function configure()
    {
        $this->setName('sf:sync')
            ->setDescription('Salesforce manual sync')
            ->addArgument('entity', InputArgument::REQUIRED, 'Entity to sync?');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = $input->getArgument('entity');
        
        $client = $this->getContainer()->get('ddeboer_salesforce_client');
        
        switch(strtolower($entity)) {
            case 'packages':
                $output->writeln('<info>deleting local storage...</info>');
                $em = $this->getContainer()->get('doctrine')->getManager();
                
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
                    'Parent_Category_Rank_del__c ' .
                    'FROM Categories__c ' .
                    'WHERE ' .
                    'Parent_Category_Rank_del__c != 0 '
                );
                
                $packageResult = $client->query('SELECT ' .
                    'Id, ' .
                    'Name, ' .
                    'ProductCode, ' .
                    'Description, ' .
                    'Location__c, ' .
                    'URL_2__c, ' .
                    'Year_Version__c, ' .
                    'Class_Version__c, ' .
                    'Suggested_Retail_Value__c, ' .
                    'Home_Page_view__c, ' .
                    'Package_Details__c, ' .
                    'Package_Category_Pairings__c, ' .
                    'New_Item__c, ' .
                    'Best_Seller__c, ' .
                    'Parent_Header__c, ' .
                    'OMIT_from_Winspire__c, ' .
                    '(SELECT Id, Name, UnitPrice FROM PricebookEntries WHERE Pricebook2Id = \'' . $this->pricebookId . '\') ' .
                    'FROM Product2 ' .
                    'WHERE ' .
                    'family = \'No-Risk Auction Package\' ' .
                    'AND IsActive = true ' .
                    'AND IsDeleted = false ' .
                    'AND Parent_Header__c != \'\''
                );
                
                
                $output->writeln('<info>storing local cache...</info>');
                
                $parentCategories = array();
                $childCategories = array();
                foreach($categoryResult as $c) {
                    if(!isset($parentCategories[$c->Parent_Category__c])) {
                        $parentCategories[$c->Parent_Category__c] = new Category();
                        $parentCategories[$c->Parent_Category__c]->setTitle($c->Parent_Category__c);
                        $parentCategories[$c->Parent_Category__c]->setNumber('');
                        $parentCategories[$c->Parent_Category__c]->setSfId('');
                        $parentCategories[$c->Parent_Category__c]->setRank(0);
                        $em->persist($parentCategories[$c->Parent_Category__c]);
                        $em->flush();
                    }
                    
                    $category = new Category();
                    $category->setParent($parentCategories[$c->Parent_Category__c]);
                    $category->setTitle($c->Child_Category__c);
                    $category->setNumber($c->Name);
                    $category->setSfId($c->Id);
                    $category->setRank($c->Parent_Category_Rank_del__c);
                    $em->persist($category);
                    $em->flush();
                    
                    if(!isset($childCategories[$category->getNumber()])) {
                        $childCategories[$category->getNumber()] = $category;
                    }
                }
                
                $count = 0;
                foreach ($packageResult as $p) {
                    $package = new Package();
                    $package->setName($p->Name);
                    $package->setParentHeader($p->Parent_Header__c);
                    $package->setCode($p->ProductCode);
                    $package->setSfId($p->Id);
                    $package->setIsOnHome($p->Home_Page_view__c);
                    $package->setIsBestSeller($p->Best_Seller__c);
                    $package->setIsNew($p->New_Item__c);
                    
                    if(isset($p->Package_Details__c)) {
                        $package->setDetails($p->Package_Details__c);
                    }
                    
                    if(isset($p->URL_2__c)) {
                        $package->setPicture($p->URL_2__c);
                    }
                    
                    $package->setIsDefault(false);
                    $package->setSuggestedRetailValue($p->Suggested_Retail_Value__c);
                    $package->setYearVersion($p->Year_Version__c);
                    
                    if(isset($p->OMIT_from_Winspire__c)) {
                        $package->setIsPrivate($p->OMIT_from_Winspire__c == '1' ? true : false);
                    }
                    
                    $categories = array();
                    if(isset($p->Package_Category_Pairings__c)) {
                        $categories = explode(';', $p->Package_Category_Pairings__c);
                    }
                    
                    foreach($categories as $category) {
                        if(isset($childCategories[$category])) {
                            $package->addCategory($childCategories[$category]);
                        }
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
                
                break;
        }
    }
}
