<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\User;

class MailChimpCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('mailchimp:sync')
            ->setDescription('Manual sync of MailChimp subscriptions');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        $mailchimp = $this->getContainer()->get('mailchimp');
        
        $query = $em->createQuery(
            'SELECT u FROM InertiaWinspireBundle:User u WHERE u.newsletter = :n and u.type = :t'
        )->setParameter('n', 1)->setParameter('t', 'C');
        
        $users = $query->getResult();
        
        
        foreach($users as $user) {
            $list = $mailchimp->getList();
            $list->setMerge(array(
                'FNAME' => $user->getFirstName(),
                'LNAME' => $user->getLastName(),
                'MMERGE3' => $user->getCompany()->getName()
            ));
            
            $result = $list->Subscribe($user->getEmail());
            
            if($result) {
                $output->writeln('<info>Added new email: ' . $user->getEmail() . '</info>');
            }
            else {
                $output->writeln('<error>Problem adding: ' . $user->getEmail() . '</error>');
            }
        }
    }
}
