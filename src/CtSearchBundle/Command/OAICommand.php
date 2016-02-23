<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class OAICommand extends ContainerAwareCommand {

  protected function configure() {
    $this
        ->setName('ctsearch:oai')
        ->setDescription('Launch OAI Harvester')
        ->addArgument('id', InputArgument::REQUIRED, 'Datasource id')
        ->addArgument('token', InputArgument::OPTIONAL, 'Resumption token')
        ->addArgument('run', InputArgument::OPTIONAL, 'Run')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $id = $input->getArgument('id');
    $token = $input->getArgument('token');
    if($token == 'NULL')
      $token = null;
    $run = $input->getArgument('run');
    if($run != null){
      $datasource = IndexManager::getInstance()->getDatasource($id, null);
      if(get_class($datasource) == 'CtSearchBundle\Datasource\OAIHarvester'){
        $datasource->setOutput($output);
        $output->writeln('Executing OAI Harvester "' . $datasource->getName() . '"');
        $datasource->runCli($token);
      }
    }
    else{
      $code = 0;
      $out = '';
      exec(PHP_BINARY . ' app/console ctsearch:oai ' . $id . ' NULL run', $out, $code);
      while($code == 9){
        $token  = $out[count($out) - 1];
        print 'Resuming with token ' . $token . PHP_EOL;
        exec(PHP_BINARY . ' app/console ctsearch:oai ' . $id . ' "' . $token . '" run', $out, $code);
      }
    }
  }
  
}