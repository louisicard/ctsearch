<?php

namespace CtSearchBundle\Command;

use CtSearchBundle\Classes\IndexManager;
use CtSearchBundle\Classes\Processor;
use CtSearchBundle\Datasource\Datasource;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportExportCommand extends ContainerAwareCommand
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
      ->setName('ctsearch:imex')
      ->setDescription('Import/Export tool')
      ->addArgument('operation', InputArgument::REQUIRED, 'Operation: import or export')
      ->addArgument('object', InputArgument::OPTIONAL, 'Object type to export')
      ->addArgument('id', InputArgument::OPTIONAL, 'Object ID to export')
    ;
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->input = $input;
    $this->output = $output;
    $operation = $input->getArgument('operation');
    $object = $input->getArgument('object');
    $id = $input->getArgument('id');
    switch($operation){
      case 'export':
        if($object != NULL && !empty($object) && $id != NULL && !empty($id)){
          $this->export($object, $id);
        }
        else{
          $output->writeln('Mising object and/or id parameter(s)');
        }
        break;
      case 'import':
        $this->import();
        break;
      default:
        $output->writeln('Operation "' . $operation . '" is not supported');
    }
  }

  private function import(){
    $stdin = '';
    while (!feof(STDIN)) {
      $stdin .= fread(STDIN, 1024);
    }
    $json = json_decode($stdin, true);
    if($json != null && isset($json['type'])){
      switch($json['type']){
        case 'datasource':
          Datasource::import($json);
          break;
        case 'processor':
          Processor::import($json);
          break;
        default:
          $this->output->writeln('Object type "' . $json['type'] . '" is not supported');
      }
    }
    else{
      $this->output->writeln('Cannot process import');
    }
  }

  private function export($object, $id){
    switch($object){
      case 'processor':
        $processor = IndexManager::getInstance()->getProcessor($id);
        if($processor != null){
          $this->output->write($processor->export());
        }
        else{
          $this->output->writeln('Processor "' . $id . '" does not exist');
        }
        break;
      case 'datasource':
        $datasource = IndexManager::getInstance()->getDatasource($id, null);
        if($datasource != null){
          $this->output->write($datasource->export());
        }
        else{
          $this->output->writeln('Datasource "' . $id . '" does not exist');
        }
        break;
      default:
        $this->output->writeln('Object type "' . $object . '" is not supported');
    }
  }

}
