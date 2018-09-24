<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Processor;
use CtSearchBundle\Datasource\Datasource;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpIndexCommand extends ContainerAwareCommand
{

  /**
   * @var OutputInterface
   */
  private $output;
  /**
   * @var InputInterface
   */
  private $input;

  protected function configure()
  {
    $this
      ->setName('ctsearch:dump')
      ->setDescription('Dump index tool')
      ->addArgument('index', InputArgument::REQUIRED, 'The name of the index to dump')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input = $input;
    $this->output = $output;
    $index = $input->getArgument('index');
    $this->dump($index);
  }

  private function dump($index, $from = 0)
  {
    $dumpSize = 1000;
    $res = IndexManager::getInstance()->search($index, json_encode(array(
        'query' => array(
          'match_all' => array(
            'boost' => 1
          )
        ))
    ), $from, $dumpSize);
    if(isset($res['hits']['hits'])) {
      foreach($res['hits']['hits'] as $hit) {
        $this->output->writeln(json_encode($hit));
      }
    }
    if(isset($res['hits']['total']) && $res['hits']['total'] > $from) {
      $this->dump($index, $from + $dumpSize);
    }
  }

}
