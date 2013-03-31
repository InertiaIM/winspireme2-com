<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\ContentPack;
use Inertia\WinspireBundle\Entity\ContentPackFile;
use Inertia\WinspireBundle\Entity\ContentPackVersion;
use Inertia\WinspireBundle\Entity\Package;

class ContentCommand extends ContainerAwareCommand
{
    private $sfUrl = 'https://c.na5.content.force.com/sfc/servlet.shepherd/version/download/';
    private $vars;
    
    protected function configure()
    {
        $this->setName('sf:content')
            ->setDescription('Salesforce content-pack grabber');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $client = $this->getContainer()->get('ddeboer_salesforce_client');
        
        $sfUsername = $this->getContainer()->parameters['ddeboer_salesforce_client.username'];
        $sfPassword = $this->getContainer()->parameters['ddeboer_salesforce_client.password'];
        $sfToken = $this->getContainer()->parameters['ddeboer_salesforce_client.token'];
        
        
        // First, we need to login to SF to obtain all the necessary security fields
        // (sid, sid_Client, etc)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://login.salesforce.com/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'un=' . $sfUsername . '&pw='  . $sfPassword .  '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        
        
        // Scrape the data from the returned HTML.
        // If/when things go wrong, this is where to check first.
        $pieces = preg_filter('/.*?ClientHash\((.*?)\);.*/us', '$1', $response, 1);
        $pieces = preg_split('/[\'\s,]+/', $pieces, -1, PREG_SPLIT_NO_EMPTY);
        
        $this->vars = array(
            'sid_Client' => $pieces[1],
            'clientSrc' => $pieces[2],
            'sid' => str_replace(array('%2521', '%21'), array('!', '!'), preg_filter('/.*sid%3D(.*?)%26.*/', '$1', $pieces[3])),
            'inst' => 'APP7'
        );
        
        
        $query = $em->createQuery(
            'SELECT p FROM InertiaWinspireBundle:Package p WHERE p.contentPack != :cp'
        )
            ->setParameter('cp', '')
        ;
        $packages = $query->getResult();
        
        foreach($packages as $p) {
            $sfId = trim(preg_replace('/.*selectedDocumentId=(.*)/i', '$1', $p->getContentPack()));
            
            $contentResult = $client->query('SELECT ' .
                'Title, ' .
                'LatestPublishedVersionId, ' .
                'Id ' .
                'FROM ContentDocument ' .
                'WHERE ' .
                'Id = \'' . $sfId . '\''
            );
            
            $cp = $contentResult->first();
            $sfId = $cp->Id;
            $title = $cp->Title;
            $versionId = $cp->LatestPublishedVersionId;
            
            $p->setSfContentPackId($sfId);
            $em->persist($p);
            $em->flush();
            
            
            $query2 = $em->createQuery(
                'SELECT c FROM InertiaWinspireBundle:ContentPack c WHERE c.sfId = :sfid'
            )
                ->setParameter('sfid', $sfId)
            ;
            
            try {
                $contentPack = $query2->getSingleResult();
            }
            catch (\Exception $e) {
                $contentPack = new ContentPack();
            }
            
            $contentPack->setSfTitle($title);
            $contentPack->setSfId($sfId);
            
            if($contentPack->getLatestSfVersionId() != $versionId) {
                // New version is available
                $contentPackVersion = new ContentPackVersion();
                $contentPackVersion->setSfId($versionId);
                
                $contentPack->setLatestSfVersionId($versionId);
                $contentPack->addVersion($contentPackVersion);
                
                $files = $this->grabFiles($ch, $versionId);
                
                foreach($files as $file) {
                    $contentPackFile = new ContentPackFile();
                    $contentPackFile->setMd5($file['md5']);
                    $contentPackFile->setName($file['name']);
                    $contentPackFile->setLocation($versionId);
                    $contentPackFile->setContentPackVersion($contentPackVersion);
                    
                    $em->persist($contentPackFile);
                    $em->flush();
                }
            }
            
            $em->persist($contentPack);
            $em->flush();
        }
        
        
        curl_close($ch);
        
//        $output->writeln('<info>retrieving SF objects...</info>');
    }
    
    protected function grabFiles(&$ch, $sfId)
    {
        $directory = $this->getContainer()->getParameter('kernel.root_dir') . '/documents/' . $sfId;
        $filename = $directory . '/' . $sfId . '.zip';
        
        
        
        $cookie = '';
        foreach($this->vars as $key => $var) {
            $cookie .= $key . '=' . $var . '; ';
        }
        
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        curl_setopt($ch, CURLOPT_URL, $this->sfUrl . $sfId);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31');
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        
echo 'GOING FOR THIS PACK:  ' . $this->sfUrl . $sfId;
echo "\n";
        
        
        if (is_dir($directory)) {
            // Does the directory already exist?
            // We must be resyncing files; so dump everything in the directory.
            if ($handle = opendir($directory)) {
                echo "Directory handle: $handle\n";
                echo "Entries:\n";
                
                while (false !== ($entry = readdir($handle))) {
                    if($entry != '.' && $entry != '..') {
                        echo "delete: $entry\n";
                        unlink($directory . '/' . $entry);
                    }
                }
                
                closedir($handle);
            }
        }
        else {
            mkdir($directory);
        }
        
        
        $fp = fopen($filename, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        
        if (curl_exec($ch) === false) {
            fclose($fp);
            unlink($sfId);
            throw new Exception('curl_exec error for url'); 
        }
        else {
            fclose($fp);
        }
        
        $zip = zip_open($filename);
        
        if($zip) {
            $files = array();
            while ($zip_entry = zip_read($zip)) {
echo "Name:               " . zip_entry_name($zip_entry) . "\n";
                
                if (zip_entry_open($zip, $zip_entry, 'r')) {
                    $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                    $fp2 = fopen($directory . '/' . zip_entry_name($zip_entry), 'w');
                    fwrite($fp2, $buf);
                    fclose($fp2);
                    
                    $files[] = array(
                        'name' => zip_entry_name($zip_entry),
                        'md5' => md5_file($directory . '/' . zip_entry_name($zip_entry))
                    );
                    
                    zip_entry_close($zip_entry);
                }
echo "\n";
            }
            zip_close($zip);
        }
        
        return $files;
    }
}