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
    private $pricebookId = '01s700000006IU7AAM';
    
    protected function configure()
    {
        $this->setName('test:content')
            ->setDescription('Test SF Content');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        
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
