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
    private $recordTypeId = '01270000000DVD5AAO';
    
    protected function configure()
    {
        $this->setName('test:command')
            ->setDescription('Test SF Command');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $client = $this->getContainer()->get('ddeboer_salesforce_client');
        
        
        $query = $em->createQuery(
            'SELECT a FROM InertiaWinspireBundle:Account a WHERE a.sfId IS NOT NULL'
        );
        
//        try {
            $accounts = $query->getResult();
//        }
//        catch (\Doctrine\Orm\NoResultException $e) {
//            echo 'problem with category lookup';
//        }
        
        
        
        foreach($accounts as $account) {
            $output->writeln('<info>' . $account->getName() . ' (' . $account->getSfId() . ')</info>');
            $output->writeln('<info>retrieving SF objects...</info>');
            
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
                'RecordTypeId ' .
                'FROM Account ' .
                'WHERE ' .
                'RecordTypeId = \'' . $this->recordTypeId . '\'' .
                'AND Id =\'' . $account->getSfId() . '\''
            );
            
            $sfAccount = $accountResult->first();
if(isset($sfAccount->Name)) {
    echo $sfAccount->Name . "\n";
}
if(isset($sfAccount->BillingStreet)) {
    echo $sfAccount->BillingStreet . "\n";
}
if(isset($sfAccount->BillingCity)) {
    echo $sfAccount->BillingCity . "\n";
}
if(isset($sfAccount->BillingState)) {
    echo $sfAccount->BillingState . "\n";
}
if(isset($sfAccount->BillingPostalCode)) {
    echo $sfAccount->BillingPostalCode . "\n";
}
if(isset($sfAccount->BillingCountry)) {
    echo $sfAccount->BillingCountry . "\n";
}
if(isset($sfAccount->OwnerId)) {
    echo $sfAccount->OwnerId . "\n";
}
        }
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
exit;
        $output->writeln('<info>retrieving SF objects...</info>');
        
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
            'RecordTypeId ' .
            'FROM Account ' .
            'WHERE ' .
            'RecordTypeId = \'' . $this->recordTypeId . '\''
//            'AND Parent_Category__c != \'US Travel\' ' .
//            'ORDER BY WEB_category_sort__c, ' .
//            'Child_Category__c'
        );
        
        foreach($accountResult as $account) {
print_r($account);
        }
exit;
//print_r($accountResult); exit;
        
        
        
        
        
        
        
        
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p'
        );
        
        $packages = $query->getResult();
        
        
        
        foreach($packages as $package) {
            
            try {
                $keywords = unserialize($package->getKeywords());
            }
            catch(\Exception $e) {
echo 'can\'t unserialize...' . "\n";
                continue;
            }
            
            if(is_array($keywords)) {
print_r(implode(' ', $keywords));
echo "\n";
                
                $package->setKeywords(implode(' ', $keywords));
                $em->persist($package);
                $em->flush();
            }
        }
    }
}
