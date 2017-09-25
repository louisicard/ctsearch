<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 26/08/2016
 * Time: 14:59
 */

namespace CtSearchBundle\Command;


use CtSearchBundle\Classes\Index;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RefactoringCommand extends ContainerAwareCommand
{
  protected function configure(){
    $this
      ->setName('ctsearch:refactoring')
      ->setDescription('Refactoring elements')
      ->addOption('drop-analyzer', null, InputOption::VALUE_REQUIRED)
      ->addOption('drop-filter', null, InputOption::VALUE_REQUIRED)
      ->addArgument('type', InputArgument::REQUIRED, 'Refactoring type : [Index, Mapping]')
      ->addArgument('source', InputArgument::REQUIRED, 'Source')
      ->addArgument('target', InputArgument::REQUIRED, 'Target')
    ;
  }

  /**
   * @var OutputInterface
   */
  protected $output;

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->output = $output;
    $type = $input->getArgument('type');
    $source = $input->getArgument('source');
    $target = $input->getArgument('target');
    if($type == 'index') {
      $this->refactorIndex($source, $target, $input->getOption('drop-analyzer'), $input->getOption('drop-filter'));
    }
  }

  private function refactorIndex($source, $target, $dropAnalyzer = NULL, $dropFilter = NULL) {
    $index = IndexManager::getInstance()->getIndex($source);
    if($index != null){
      $settings = json_decode($index->getSettings(), true);
      if($dropAnalyzer != NULL){
        $dropAnalyzers = array_map('trim', explode(',', $dropAnalyzer));
        foreach($dropAnalyzers as $toDrop){
          if(isset($settings['analysis']['analyzer'][$toDrop])){
            unset($settings['analysis']['analyzer'][$toDrop]);
          }
        }
      }
      if($dropFilter != NULL){
        $dropFilters = array_map('trim', explode(',', $dropFilter));
        foreach($dropFilters as $toDrop){
          if(isset($settings['analysis']['filter'][$toDrop])){
            unset($settings['analysis']['filter'][$toDrop]);
          }
        }
      }
      $targetExists = IndexManager::getInstance()->getIndex($target) != NULL;
      if(!$targetExists) {
        $new = new Index($target, json_encode($settings));
        IndexManager::getInstance()->createIndex($new);
      }
      else{
        $this->output->writeln('Target index already exists');
      }
    }
    else{
      $this->output->writeln('Source index does not exist');
    }
  }

}