<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\ContentPackVersion;

class DumpCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('dump:email')
            ->setDescription('Dump Command');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $mailer = $this->getContainer()->get('mailer');
        $templating = $this->getContainer()->get('templating');
        
        $query = $em->createQuery(
            'SELECT s FROM InertiaWinspireBundle:Suitcase s where s.id=644'
        );
        $suitcase = $query->getSingleResult();
        
        
        $user = $suitcase->getUser();
        $account = $user->getCompany();
        
        $name = $user->getFirstName() . ' ' .
            $user->getLastName();
        
        $email = $user->getEmail();
        
        $message = \Swift_Message::newInstance()
            ->setSubject('Your Booking Vouchers are ready to deliver!')
            ->setFrom(array('info@winspireme.com' => 'Winspire'))
            ->setTo(array($email => $name))
            ->setBody(
                $templating->render(
                    'InertiaWinspireBundle:Email:travel-vouchers-ready.html.twig',
                    array(
                        'suitcase' => $suitcase
                    )
                ),
                'text/html'
            )
            ->addPart(
                $templating->render(
                    'InertiaWinspireBundle:Email:travel-vouchers-ready.txt.twig',
                    array(
                        'suitcase' => $suitcase
                    )
                ),
                'text/plain'
            )
            ;
//            $message->setBcc($account->getSalesperson()->getEmail());
            $message->setBcc('doug@inertiaim.com');
            
            $mailer->send($message);
    }
}