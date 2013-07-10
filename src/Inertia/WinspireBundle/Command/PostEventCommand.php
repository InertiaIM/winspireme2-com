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
            
            $message = \Swift_Message::newInstance()
                ->setSubject('Congratulations on a Successful Event')
                ->setFrom(array('info@winspireme.com' => 'Winspire'))
                ->setTo(array($email => $name))
                ->setBcc(array($suitcase->getUser()->getCompany()->getSalesperson()->getEmail(), 'doug@inertiaim.com'))
                ->setBody(
                    $templating->render(
                        'InertiaWinspireBundle:Email:congrats-invoice-directions.html.twig',
                        array('suitcase' => $suitcase)
                    ),
                    'text/html'
                )
                ->addPart(
                    $templating->render(
                        'InertiaWinspireBundle:Email:congrats-invoice-directions.txt.twig',
                        array('suitcase' => $suitcase)
                    ),
                    'text/plain'
                )
            ;
            
            $mailer->send($message);
        }
    }
}