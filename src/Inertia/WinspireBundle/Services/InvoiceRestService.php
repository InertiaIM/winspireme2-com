<?php
namespace Inertia\WinspireBundle\Services;

use Ddeboer\Salesforce\ClientBundle\Client;
use Doctrine\ORM\EntityManager;
use Inertia\WinspireBundle\Entity\Suitcase;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Templating\EngineInterface;


class InvoiceRestService
{
    protected $em;
    protected $sf;
    protected $logger;
    protected $mailer;
    protected $templating;
    
    private $recordTypeId = '01270000000DVD5AAO';
    
    public function __construct(Client $salesforce, EntityManager $entityManager, Logger $logger, \Swift_Mailer $mailer, EngineInterface $templating)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->templating = $templating;
    }
    
    public function handle(Request $request)
    {
        // TODO pass the path into the service?
        $directory = '/var/www/winspire.inertia.im/app/invoices/';
        
        $sfId = $request->query->get('id');
        
        $this->logger->info('Here comes an Invoice...');
        $this->logger->info($sfId);
        
        
        $attachmentResult = $this->sf->queryAll('SELECT ' .
            'Id, ' .
            'Name, ' .
            'Body, ' .
            'BodyLength, ' .
            'SystemModstamp, ' .
            'ParentId, ' .
            'IsDeleted ' .
            'FROM Attachment ' .
            'WHERE Id = \'' . $sfId . '\''
        );
        
        
        if (count($attachmentResult) > 0) {
            $sfInvoice = $attachmentResult->first();
            
            // Is this file related to a Suitcase (Opportunity) that we're concerned with?
            $existingSuitcase = $this->em->getRepository('InertiaWinspireBundle:Suitcase')->findOneBySfId($sfInvoice->ParentId);
            if ($existingSuitcase) {
                $directory .= $existingSuitcase->getSfId();
                
                // Check whether it's an invoice (attachment) we've already grabbed
                if ($existingSuitcase->getInvoiceProvidedAt() != '') {
                    
                    // Are we deleting it?
                    if ($sfInvoice->IsDeleted) {
$this->logger->info('This invoice was deleted; so we\'ll unlink it');
                        if (unlink($directory . '/' . $sfInvoice->Name)) {
                            $existingSuitcase->setInvoiceProvidedAt(null);
                        }
                    }
                    
                    // Are we changing the name of the file?
                    if (false) {
                    }
                }
                else {
                    // Looks like it's new to the web site...
$this->logger->info('This invoice is new; so we\'ll save it');
                }
            }
            else {
                $directory .= 'misc/';
                // This is a mystery invoice (for testing purposes, let's work with it)
$this->logger->info('This invoice is not for us; but we\'ll save (or delete) it for testing');
                
                if ($sfInvoice->IsDeleted) {
                    // If the directory already exists, then we remove all files
                    // and then remove the directory
                    if (is_dir($directory . $sfInvoice->ParentId)) {
                        if ($dh = opendir($directory . $sfInvoice->ParentId)) {
                            while (($file = readdir($dh)) !== false) {
                                unlink($directory . $sfInvoice->ParentId . '/' . $file);
                            }
                            closedir($dh);
                        }
                        
                        rmdir($directory . $sfInvoice->ParentId);
                    }
//                    if (file_exists($directory . $sfInvoice->ParentId . '/' . $sfInvoice->Name)) {
//                        unlink($directory . $sfInvoice->ParentId . '/' . $sfInvoice->Name);
//                    }
                }
                else {
                    if (!is_dir($directory . $sfInvoice->ParentId)) {
                        mkdir($directory . $sfInvoice->ParentId);
                    }
                    else {
                        if ($dh = opendir($directory . $sfInvoice->ParentId)) {
                            while (($file = readdir($dh)) !== false) {
                                unlink($directory . $sfInvoice->ParentId . '/' . $file);
                            }
                            closedir($dh);
                        }
                    }
                    $fp = fopen($directory . $sfInvoice->ParentId . '/' . $sfInvoice->Name, 'w');
                    fwrite($fp, $sfInvoice->Body);
                    fclose($fp);
                }
            }
        }
        else {
$this->logger->info('Why aren\'t we retrieving this invoice?');
        }
        
        return true;
    }
}