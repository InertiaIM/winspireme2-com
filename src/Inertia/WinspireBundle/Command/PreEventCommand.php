<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\Suitcase;

class PreEventCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('winspire:preevent')
            ->setDescription('Send pre event informational emails');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $templating = $this->getContainer()->get('templating');
        $mailer = $this->getContainer()->get('mailer');
        
        
        // Batch of events that are coming tomorrow
        $tomorrow = new \DateTime('tomorrow');
        
$output->writeln('<info>Processing Events for (tomorrow): ' . $tomorrow->format('Y-m-d') . '</info>');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.status IN (\'P\', \'U\') AND s.eventDate = :date'
        );
        $query->setParameter('date', $tomorrow->format('Y-m-d'));
        $suitcases = $query->getResult();
        
        foreach($suitcases as $suitcase) {
$output->writeln('<info>    * ' . $suitcase->getUser()->getCompany()->getName() . ' (' . $suitcase->getName() . ')</info>');
            
            // Send Mail Messages
            $name = $suitcase->getUser()->getFirstName() . ' ' .
                $suitcase->getUser()->getLastName();
            
            $email = $suitcase->getUser()->getEmail();
            
            $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());
            
            $message = \Swift_Message::newInstance()
                ->setSubject('Good Luck at your Event!')
                ->setSender(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array($email => $name))
                ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
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
                        'InertiaWinspireBundle:Email:goodluck.html.twig',
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
                        'InertiaWinspireBundle:Email:goodluck.txt.twig',
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
        
        // Batch of events that are coming within 30 days
        $future = new \DateTime('+30 days');
        
        $output->writeln('<info>Processing Events for (+30 days): ' . $future->format('Y-m-d') . '</info>');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.status = \'U\' AND s.eventDate = :date'
        );
        $query->setParameter('date', $future->format('Y-m-d'));
        $suitcases = $query->getResult();
        
        foreach($suitcases as $suitcase) {
$output->writeln('<info>    * ' . $suitcase->getUser()->getCompany()->getName() . ' (' . $suitcase->getName() . ')</info>');
            
            $name = $suitcase->getUser()->getFirstName() . ' ' .
                $suitcase->getUser()->getLastName();
            
            $email = $suitcase->getUser()->getEmail();
            
            $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());
            
            $message = \Swift_Message::newInstance()
                ->setSubject('Remember to Pack your Suitcase!')
                ->setSender(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array($email => $name))
                ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
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
                        'InertiaWinspireBundle:Email:get-packin-reminder.html.twig',
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
                        'InertiaWinspireBundle:Email:get-packin-reminder.txt.twig',
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
        
        
        // Batch of Unpacked Suitcases from yesterday
        $yesterday = new \DateTime('yesterday');
        
        $output->writeln('<info>Processing Unpacked Suitcases from yesterday: ' . $yesterday->format('Y-m-d') . '</info>');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s WHERE s.status = \'U\' AND s.unpackedAt LIKE \'' . $yesterday->format('Y-m-d') . '%\''
        );
        $suitcases = $query->getResult();
        
        foreach($suitcases as $suitcase) {
            $output->writeln('<info>    * ' . $suitcase->getUser()->getCompany()->getName() . ' (' . $suitcase->getName() . ')</info>');
            
            $name = $suitcase->getUser()->getFirstName() . ' ' .
                $suitcase->getUser()->getLastName();
            
            $email = $suitcase->getUser()->getEmail();
            
            $locale = strtolower($suitcase->getUser()->getCompany()->getCountry());
            
            $message = \Swift_Message::newInstance()
                ->setSubject('Please confirm changes to your Suitcase')
                ->setSender(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array($email => $name))
                ->setBcc(array('doug@inertiaim.com'))
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
                        'InertiaWinspireBundle:Email:suitcase-unpacked.html.twig',
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
                        'InertiaWinspireBundle:Email:suitcase-unpacked.txt.twig',
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
    }
}