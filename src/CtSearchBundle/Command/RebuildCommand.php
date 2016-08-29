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

class RebuildCommand extends ContainerAwareCommand
{
  protected function configure(){
    $this
      ->setName('ctsearch:rebuild')
      ->setDescription('Rebuild index')
      ->addArgument('mapping', InputArgument::REQUIRED, 'Mapping')
    ;
  }
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $mapping = $input->getArgument('mapping');
    if(count(explode('.', $mapping)) == 2){
      $index = explode('.', $mapping)[0];
      $mapping_name = explode('.', $mapping)[1];
      $this->iterate($index, $mapping_name, 0, 1000, function($index, $hit){
        print $index . ' -> Reindexing ' . $hit['_id'] . PHP_EOL;
        $indexManager = IndexManager::getInstance();
        $doc = $hit['_source'];
        $doc['_id'] = $hit['_id'];
        $indexManager->indexDocument($hit['_index'], $hit['_type'], $doc, true);
      });
    }
    else{
      $output->writeln('Incorrect mapping');
    }
  }

  private function iterate($index_name, $mapping, $from, $size, $callback){
    $res = IndexManager::getInstance()->search($index_name, '{"query":{"match_all":{}}}', $from, $size, $mapping);

    if(isset($res['hits']['hits'])) {
      foreach ($res['hits']['hits'] as $index => $hit) {
        $callback($index + $from + 1, $hit);
      }
      $from += $size;
      if($res['hits']['total'] > $from){
        $this->iterate($index_name, $mapping, $from, $size, $callback);
      }
    }
  }
}