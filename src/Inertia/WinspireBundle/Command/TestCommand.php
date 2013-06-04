<?php
namespace Inertia\WinspireBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Inertia\WinspireBundle\Entity\ContentPackVersion;

class TestCommand extends ContainerAwareCommand
{
    private $recordTypeId = '01270000000DVD5AAO';
    
    protected function configure()
    {
        $this->setName('test:command')
            ->setDescription('Test Command');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();
        
        $query = $em->createQuery(
            'SELECT cv FROM InertiaWinspireBundle:ContentPackVersion cv'
        );
        $contentPackVersions = $query->getResult();
        
        $output->writeln('<info>retrieving Content Pack Version data...</info>');
        
        $directory = $this->getContainer()->getParameter('kernel.root_dir') . '/documents/';
        
        foreach ($contentPackVersions as $version) {
            if (is_dir($directory . $version->getSfId())) {
                foreach ($version->getFiles() as $file) {
                    if (is_file($directory . $version->getSfId() . '/' . $file->getName())) {
                        // This is what we want... so nothing to report
                    }
                    else {
                        $output->writeln('<error>Missing File: ' . $directory . $version->getSfId() . '/' . $file->getName() .  ' (id: ' . $file->getId() . ')</error>');
                    }
                }
            }
            else {
                $output->writeln('<error>Missing Directory: ' . $version->getSfId() . ' (id:' . $version->getId() . ' / ' . $version->getUpdated()->format('Y-m-d') . ')</error>');
            }
        }
    }
}