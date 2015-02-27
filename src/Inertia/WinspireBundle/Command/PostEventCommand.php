<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Suitcase;

class PostEventCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('winspire:postevent')
            ->setDescription('Send post event informational email');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Completed event "congratulations" email
        $yesterday = new \DateTime('yesterday');
        
$output->writeln('<info>Processing Events for: ' . $yesterday->format('Y-m-d') . '</info>');
        
        $em = $this->getContainer()->get('doctrine')->getManager();
        $templating = $this->getContainer()->get('templating');
        $mailer = $this->getContainer()->get('mailer');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.status IN (\'P\', \'U\') AND s.eventDate = :date'
        );
        $query->setParameter('date', $yesterday->format('Y-m-d'));
        $suitcases = $query->getResult();
        
        foreach($suitcases as $suitcase) {
$output->writeln('<info>    * ' . $suitcase->getUser()->getCompany()->getName() . ' (' . $suitcase->getName() . ')</info>');
            
            // Send Mail Messages
            $name = $suitcase->getUser()->getFirstName() . ' ' .
                $suitcase->getUser()->getLastName();
            
            $email = $suitcase->getUser()->getEmail();
            
            $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());
            
            $message = \Swift_Message::newInstance()
                ->setSubject('Congratulations on a Successful Event')
                ->setSender(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array($email => $name))
                ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail()))
            ;

            if ($suitcase->getUser()->getCompany()->getSalesperson()->getId() != 1) {
                $sperson = $suitcase->getUser()->getCompany()->getSalesperson();
                $message->setReplyTo(array($sperson->getEmail() => $sperson->getFirstName() . ' ' . $sperson->getLastName()));
                $message->setFrom(array($sperson->getEmail() => $sperson->getFirstName() . ' ' . $sperson->getLastName()));
                $from = $sperson->getEmail();
            }
            else {
                $message->setFrom(array('info@winspireme.com' => 'Winspire'));
                $from = 'info@winspireme.com';
            }

            $message
                ->setBody(
                    $templating->render(
                        'InertiaWinspireBundle:Email:congrats-invoice-directions.html.twig',
                        array(
                            'suitcase' => $suitcase,
                            'from' => $from,
                            'locale' => $locale,
                        )
                    ),
                    'text/html'
                )
                ->addPart(
                    $templating->render(
                        'InertiaWinspireBundle:Email:congrats-invoice-directions.txt.twig',
                        array(
                            'suitcase' => $suitcase,
                            'from' => $from,
                            'locale' => $locale,
                        )
                    ),
                    'text/plain'
                )
            ;
            
            $mailer->send($message);
        }
        
        
        // Past event "expiration" (60 Days and Unpacked)
        $sf = $this->getContainer()->get('ddeboer_salesforce_client');
        
        $past = new \DateTime('60 days ago');
        
        $output->writeln('<info>Processing "Expirations" for: ' . $past->format('Y-m-d') . '</info>');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.status IN (\'U\') AND s.eventDate = :date'
        );
        $query->setParameter('date', $past->format('Y-m-d'));
        $suitcases = $query->getResult();
        
        foreach($suitcases as $suitcase) {
            $output->writeln('<info>    * ' . $suitcase->getEventName() . ' (' . $suitcase->getEventDate()->format('Y-m-d') . ')</info>');
            
            if ($suitcase->getSfId() != '') {
                $sfOpportunity = new \stdClass();
                $sfOpportunity->Id = $suitcase->getSfId();
                $sfOpportunity->StageName = 'Lost';
                $sfOpportunity->Objections__c = 'To Be Confirmed';
                $sfOpportunity->Website_suitcase_status__c = 'Deleted';
                
                try {
                    $saveResult = $sf->update(array($sfOpportunity), 'Opportunity');
                    if ($saveResult[0]->success) {
                        $this->sendEmail('Suitcase Expired (success)',
                            'Suitcase: ' . $suitcase->getId() . "\n" .
                            'Event Date: ' . $suitcase->getEventDate()->format('Y-m-d')
                        );
                        
                        $em->remove($suitcase);
                        $em->flush();
                    }
                    else {
                        $this->sendEmail('Suitcase Expired (failed on delete)',
                            'Suitcase: ' . $suitcase->getId() . "\n" .
                            'Event Date: ' . $suitcase->getEventDate()->format('Y-m-d')
                        );
                    }
                }
                catch (\Exception $e) {
                    $this->sendEmail('Suitcase Expired (failed on SF)',
                        'Suitcase: ' . $suitcase->getId() . "\n" .
                        'Event Date: ' . $suitcase->getEventDate()->format('Y-m-d')
                    );
                }
            }
        }
    }
    
    protected function sendEmail($subject, $text) {
        $mailer = $this->getContainer()->get('mailer');
        
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo('iim@inertiaim.com')
            ->setBody($text, 'text/plain')
        ;
        
        $mailer->send($message);
    }
}