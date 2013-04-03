<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Category;
use Inertia\WinspireBundle\Entity\Package;

class TestCommand extends ContainerAwareCommand
{
    private $pricebookId = '01s700000006IU7AAM';
    
    protected function configure()
    {
        $this->setName('test:content')
            ->setDescription('Test SF Content');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = $this->getContainer()->get('ddeboer_salesforce_client');
        
        $output->writeln('<info>retrieving SF objects...</info>');
        
        
        $id = '01t70000004OdOeAAK';
        
        
        
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
            'AND Parent_Header__c != \'\' ' .
            'AND Id = \''. $id . '\''
        );
        
        
//        print_r($packageResult);
        
        
echo count($packageResult);
//print_r($packageResult);
        foreach($packageResult as $c) {
        
print_r($c);
        }
    }
}
