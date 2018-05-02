<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Processor;
use CtSearchBundle\Datasource\Datasource;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
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
      ->setName('ctsearch:import')
      ->setDescription('import tool')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input = $input;
    $this->output = $output;
    while($line = fgets(STDIN)){
      $this->index($line);
    }
    IndexManager::getInstance()->bulkIndex($this->buffer);
    IndexManager::getInstance()->flush();
    $this->output->writeln($this->total . ' documents indexed');
  }

  private $count = 0;
  private $total = 0;
  private $buffer = [];

  private function index($item) {
    $data = json_decode($item, TRUE);
    $data['_source']['_id'] = $data['_id'];
    $this->count++;
    $this->total++;
    $this->buffer[] = array(
      'indexName' => $data['_index'],
      'mappingName' => $data['_type'],
      'body' => $data['_source'],
    );
    if($this->count >= 1000) {
      IndexManager::getInstance()->bulkIndex($this->buffer);
      $this->count = 0;
      $this->output->writeln($this->total . ' documents indexed so far');
    }
  }

}
