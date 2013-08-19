<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\ContentPackVersion;

class TestCommand extends ContainerAwareCommand
{
    private $recordTypeId = '01270000000DVD5AAO';
    private $em;
    private $output;
    
    protected function configure()
    {
        $this->setName('test:command')
            ->setDescription('Test Command');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $sf = $this->getContainer()->get('ddeboer_salesforce_client');
        $this->em = $this->getContainer()->get('doctrine')->getManager();
        $this->output = $output;
        
        $packageResult = $sf->query('SELECT ' .
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
            'AND Available__c = \'NO\' '
        );
        
        foreach ($packageResult as $p) {
            // Test whether this package is already in our database
            $this->output->writeln('<info>Package: ' . $p->Parent_Header__c . ' (' . $p->ProductCode . ')</info>');
            $package = $this->em->getRepository('InertiaWinspireBundle:Package')->findOneBySfId($p->Id);
            if (!$package) {
                $this->output->writeln('<info>Not Found</info>');
                continue;
            }
            
            $this->output->writeln('<info>Found</info>');
            
            if (isset($p->Available__c)) {   
                $processRemoval = false;
                if ($package->getAvailable() && ($p->Available__c == 'NO')) {
                    // Changing from Available -> Unavailable
                    $processRemoval = true;
                }
                
                $package->setAvailable($p->Available__c == 'NO' ? false : true);
                if (!$package->getAvailable()) {
                    $this->output->writeln('<info>UNAVAILABLE package (' . $p->Id . ')</info>');
                }
                
                if ($processRemoval) {
                    $this->output->writeln('<info>Process Removal of package due to UNAVAILABLE (' . $p->Id . ')</info>');
                    
                    // TODO is it necessary to delete the package even if it's possible?
                    $deletePackage = $this->processRemoval($package);
                }
            }
            else {
                $package->setAvailable(true);
            }
            
            $this->em->persist($package);
            $this->em->flush();
        }
    }
    
    protected function getAssociatedSuitcases($package) {
        $suitcases = array();
        foreach ($package->getSuitcaseItems() as $item) {
            $this->output->writeln('<info>    Retrieving Associated Suitcase: ' . $item->getSuitcase()->getId() . '</info>');
            $suitcases[] = $item->getSuitcase();
        }
        
        return $suitcases;
    }
    
    protected function processRemoval($package) {
        $suitcases = $this->getAssociatedSuitcases($package);
        
        if (count($suitcases) == 0) {
            // No Suitcases contain this Package so it's safe
            // to delete without further processing
            $this->output->writeln('<info>        No Suitcases to worry about for Package removal</info>');
            
            return true;
        }
        else {
            foreach ($suitcases as $suitcase) {
                if ($suitcase->getStatus() == 'U') {
                    // Unpacked Suitcase
                    // Remove the item from the Suitcase and email NP
                    $this->output->writeln('<info>        Unpacked Suitcase contains inactive package: ' . $suitcase->getId() . '</info>');
                    
                    foreach ($suitcase->getItems() as $item) {
                        if ($item->getPackage()->getId() == $package->getId()) {
                            // Only send a message if NP hasn't marked the package for deletion,
                            // and the event date is in the future...
                            if ($item->getStatus() == 'X' || ($suitcase->getEventDate() < new \DateTime())) {
                                $this->output->writeln('<info>        No need to send the email message (Deleted Item or Event Date passed).</info>');
                            }
                            $this->em->remove($item);
                            $this->em->flush();
                        }
                    }
                }
                
                if ($suitcase->getStatus() == 'P') {
                    // Packed Suitcase
                    $this->output->writeln('<info>        Packed Suitcase contains inactive package: ' . $suitcase->getId() . '</info>');
                }
            }
            
            return true;
        }
    }
}