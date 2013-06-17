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
    protected $directory;
    
    public function __construct(
        Client $salesforce,
        EntityManager $entityManager,
        Logger $logger,
        \Swift_Mailer $mailer,
        EngineInterface $templating,
        $directory)
    {
        $this->sf = $salesforce;
        $this->em = $entityManager;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->directory = $directory;
    }
    
    public function handle(Request $request)
    {
        $sfIds = $request->request->get('id');
        
        foreach($sfIds as $sfId) {
        
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
                $this->directory .= $existingSuitcase->getSfId();
                
                // Check whether it's an invoice (attachment) we have and need to delete
                if ($existingSuitcase->getInvoiceProvidedAt() != '' && $sfInvoice->IsDeleted) {
$this->logger->info('This invoice was deleted; so we\'ll unlink it');
                    if (unlink($this->directory . '/' . $sfInvoice->Name)) {
                        $existingSuitcase->setInvoiceProvidedAt(null);
                        $existingSuitcase->setInvoiceFileName(null);
                        $existingSuitcase->setStatus('R');
                        $this->em->persist($existingSuitcase);
                        $this->em->flush();
                    }
                }
                else {
                    // The invoice is either new or updated
$this->logger->info('This invoice is new or updated; so we\'ll save it');
                    if (!is_dir($this->directory)) {
                        mkdir($this->directory);
                    }
                    else {
                        if ($dh = opendir($this->directory)) {
                            while (($file = readdir($dh)) !== false) {
                                if (is_file($this->directory . '/' . $file)) {
                                    unlink($this->directory . '/' . $file);
                                }
                            }
                            closedir($dh);
                        }
                    }
                    
                    $fp = fopen($this->directory . '/' . $sfInvoice->Name, 'w');
                    if (fwrite($fp, $sfInvoice->Body)) {
                        $existingSuitcase->setInvoiceFileName($sfInvoice->Name);
                        $existingSuitcase->setInvoiceProvidedAt(new \DateTime());
                        $existingSuitcase->setStatus('I');
                        $this->em->persist($existingSuitcase);
                        $this->em->flush();
                        $this->sendEmail($existingSuitcase);
                    }
                    else {
$this->logger->info('Problems saving this invoice.');
                    }
                    fclose($fp);
                }
            }
            else {
                $this->directory .= 'misc/';
                // This is a mystery invoice (for testing purposes, let's work with it)
$this->logger->info('This invoice is not for us; but we\'ll save (or delete) it for testing');
                
                if ($sfInvoice->IsDeleted) {
                    // If the directory already exists, then we remove all files
                    // and then remove the directory
                    if (is_dir($this->directory . $sfInvoice->ParentId)) {
                        if ($dh = opendir($this->directory . $sfInvoice->ParentId)) {
                            while (($file = readdir($dh)) !== false) {
                                if (is_file($this->directory . $sfInvoice->ParentId . '/' . $file)) {
                                    unlink($this->directory . $sfInvoice->ParentId . '/' . $file);
                                }
                            }
                            closedir($dh);
                        }
                        
                        rmdir($this->directory . $sfInvoice->ParentId);
                    }
                }
                else {
                    if (!is_dir($this->directory . $sfInvoice->ParentId)) {
                        mkdir($this->directory . $sfInvoice->ParentId);
                        
                    }
                    else {
                        if ($dh = opendir($this->directory . $sfInvoice->ParentId)) {
                            while (($file = readdir($dh)) !== false) {
                                if (is_file($this->directory . $sfInvoice->ParentId . '/' . $file)) {
                                    unlink($this->directory . $sfInvoice->ParentId . '/' . $file);
                                }
                            }
                            closedir($dh);
                        }
                    }
                    $fp = fopen($this->directory . $sfInvoice->ParentId . '/' . $sfInvoice->Name, 'w');
                    fwrite($fp, $sfInvoice->Body);
                    fclose($fp);
                }
            }
        }
        else {
$this->logger->info('Why aren\'t we retrieving this invoice?');
        }
        }
        
        return true;
    }
    
    protected function sendEmail($suitcase)
    {
        $name = $suitcase->getUser()->getFirstName() . ' ' .
            $suitcase->getUser()->getLastName();
        
        $email = $suitcase->getUser()->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Winspire Invoice')
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:invoice-attached.html.twig',
                    array(
                        'suitcase' => $suitcase
                    )
                ),
                'text/html'
            )
            ->addPart(
                $this->templating->render(
                    'InertiaWinspireBundle:Email:invoice-attached.txt.twig',
                    array(
                        'suitcase' => $suitcase
                    )
                ),
                'text/plain'
            )
        ;
        
        $attachment = \Swift_Attachment::fromPath($this->directory . '/' . $suitcase->getInvoiceFileName(), 'application/pdf');
        $attachment->setFilename($suitcase->getInvoiceFileName());
        $message->attach($attachment);
        
        $message->setBcc($suitcase->getUser()->getCompany()->getSalesperson()->getEmail());
        $message->setBcc('doug@inertiaim.com');
        
        
        $this->mailer->send($message);
    }
}
