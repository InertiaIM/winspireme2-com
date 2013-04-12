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
        $accounts = $query->getResult();
        
        
        
        foreach($accounts as $account) {
            $output->writeln('<info>' . $account->getName() . ' (' . $account->getSfId() . ')</info>');
            $output->writeln('<info>retrieving SF object...</info>');
            
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
            if(!$sfAccount || !isset($sfAccount->Id)) {
                $output->writeln('<error>    Something wrong with ' . $account->getSfId() . '</error>');
                continue;
            }
            $account->setSfId($sfAccount->Id);
            
            // ACCOUNT NAME
            if(isset($sfAccount->Name)) {
                if ($account->getName() != $sfAccount->Name) {
                    $output->writeln('<info>    Old Account Name in SF: ' .  $sfAccount->Name . '</info>');
                    $output->writeln('<info>    New Account Name in SF: ' .  $account->getName() . '</info>');
                    $sfAccount->Name = $account->getName();
                }
            }
            else {
                $output->writeln('<info>    Old Account Name in SF: none</info>');
                $output->writeln('<info>    New Account Name in SF: ' .  $account->getName() . '</info>');
                $sfAccount->Name = $account->getName();
            }
            
            // ACCOUNT BILLING STREET
            if(isset($sfAccount->BillingStreet)) {
                if ($account->getAddress() != $sfAccount->BillingStreet) {
                    $output->writeln('<info>    Old STREET in SF: ' .  $sfAccount->BillingStreet . '</info>');
                    $output->writeln('<info>    New STREET in SF: ' .  $account->getAddress() . '</info>');
                    $sfAccount->BillingStreet = $account->getAddress();
                    if ($account->getAddress2() != '') {
                        $sfAccount->BillingStreet = $account->getAddress() . chr(10) . $account->getAddress2();
                    }
                }
            }
            else {
                if ($account->getAddress() != '') {
                    $output->writeln('<info>    Old STREET in SF: none</info>');
                    $output->writeln('<info>    New STREET in SF: ' .  $account->getAddress() . '</info>');
                    if ($account->getAddress2() != '') {
                        $sfAccount->BillingStreet = $account->getAddress() . chr(10) . $account->getAddress2();
                    }
                }
            }
            
            // ACCOUNT BILLING CITY
            if(isset($sfAccount->BillingCity)) {
                if ($account->getCity() != $sfAccount->BillingCity) {
                    $output->writeln('<info>    Old CITY in SF: ' . $sfAccount->BillingCity . '</info>');
                    $output->writeln('<info>    New CITY in SF: ' .  $account->getCity() . '</info>');
                    $sfAccount->BillingCity = $account->getCity();
                }
            }
            else {
                if ($account->getCity() != '') {
                    $output->writeln('<info>    Old CITY in SF: none</info>');
                    $output->writeln('<info>    New CITY in SF: ' .  $account->getCity() . '</info>');
                    $sfAccount->BillingCity = $account->getCity();
                }
            }
            
            // ACCOUNT BILLING STATE
            if(isset($sfAccount->BillingState)) {
                if ($account->getState() != $sfAccount->BillingState) {
                    $output->writeln('<info>    Old STATE in SF: ' . $sfAccount->BillingState . '</info>');
                    $output->writeln('<info>    New STATE in SF: ' . $account->getState() . '</info>');
                    $sfAccount->BillingState = $account->getState();
                }
            }
            else {
                if ($account->getState() != '') {
                    $output->writeln('<info>    Old STATE in SF: none</info>');
                    $output->writeln('<info>    New STATE in SF: ' .  $account->getState() . '</info>');
                    $sfAccount->BillingState = $account->getState();
                }
            }
            
            
            // ACCOUNT BILLING ZIP
            if(isset($sfAccount->BillingPostalCode)) {
                if ($account->getZip() != $sfAccount->BillingPostalCode) {
                    $output->writeln('<info>    Old ZIP in SF: ' . $sfAccount->BillingPostalCode . '</info>');
                    $output->writeln('<info>    New ZIP in SF: ' . $account->getZip() . '</info>');
                    $sfAccount->BillingPostalCode = $account->getZip();
                }
            }
            else {
                if ($account->getZip() != '') {
                    $output->writeln('<info>    Old ZIP in SF: none</info>');
                    $output->writeln('<info>    New ZIP in SF: ' .  $account->getZip() . '</info>');
                    $sfAccount->BillingPostalCode = $account->getZip();
                }
            }
            
            
//            if(isset($sfAccount->BillingCountry)) {
//                echo $sfAccount->BillingCountry . "\n";
//            }
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
                }
            }
            else {
                $output->writeln('<error>    Missing OwnerId?!?!</error>');
            }
            
            
            
            if ($account->getReferred() != '') {
                $sfAccount->Referred_by__c = $account->getReferred();
                $output->writeln('<info>    New REFERRED in SF: ' .  $account->getReferred() . '</info>');
            }
            
            
            $output->writeln('<info>    Updating SF record...</info>');
            $result = $client->update(array($sfAccount), 'Account');
            
            if(!$result[0]->success) {
                $output->writeln('<error>    Error updating SF record...</error>');
                print_r($result); exit;
            }
            
            $em->persist($account);
            $em->flush();
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
