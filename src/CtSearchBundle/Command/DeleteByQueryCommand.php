<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DeleteByQueryCommand extends ContainerAwareCommand {
  
  protected function configure(){
    $this
        ->setName('ctsearch:delete-by-query')
        ->setDescription('Delete records by query')
        ->addArgument('id', InputArgument::REQUIRED, 'Saved query id')
      ->addOption('no-proxy', null, InputOption::VALUE_NONE, 'Bypass proxy to connect to ES server')
    ;
  }
  protected function execute(InputInterface $input, OutputInterface $output)
    {
      if($input->getOption('no-proxy')){
        $proxy = getenv("http_proxy");
        putenv("http_proxy=");
      }
      $id = $input->getArgument('id');
      $query = IndexManager::getInstance()->getSavedQuery($id);
      if($query != null) {
        $output->writeln('Query def => ' . json_encode(json_decode($query['definition'])) . '');
        $output->writeln('Query target => ' . $query['target'] . '');
        $index = strpos($query['target'], '.') !== 0 ? explode('.', $query['target'])[0] : '.' . explode('.', $query['target'])[1];
        $mapping = strpos($query['target'], '.') !== 0 ? explode('.', $query['target'])[1] : explode('.', $query['target'])[2];
        $output->writeln('Index name => ' . $index . '');
        $output->writeln('Mapping name => ' . $mapping . '');
        $r = IndexManager::getInstance()->search($index, $query['definition']);
        if(isset($r['hits']['total'])){
          $output->writeln('Found ' . $r['hits']['total'] . ' matching record(s)');
        }
        IndexManager::getInstance()->deleteByQuery($index, $mapping, json_decode($query['definition'], true));
        $output->writeln('Query has been executed for deletion');
      }
      else{
        $output->writeln('ERROR : Query could not be found');
      }
    }
}
