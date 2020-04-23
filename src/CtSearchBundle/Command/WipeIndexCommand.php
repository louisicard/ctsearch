<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 26/08/2016
 * Time: 14:59
 */

namespace CtSearchBundle\Command;


use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WipeIndexCommand extends ContainerAwareCommand
{
    protected function configure(){
        $this
            ->setName('ctsearch:wipe')
            ->setDescription('Rebuild index')
            ->addArgument('index', InputArgument::REQUIRED, 'Input')
            ->addArgument('mapping', InputArgument::REQUIRED, 'Input')


        ;
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $index = $input->getArgument('index');
        $mapping = $input->getArgument('mapping');


        $indexManager = indexManager::getInstance();
        $indexManager->deleteByQuery($index, $mapping, array(
            'query' => array(
                'match_all' => array('boost' => 1)
            )
        ));

        $output->writeln($index.' index has been wiped out!');

    }

}