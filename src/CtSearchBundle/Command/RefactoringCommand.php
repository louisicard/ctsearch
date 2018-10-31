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
use CtSearchBundle\Classes\Mapping;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RefactoringCommand extends ContainerAwareCommand
{
  protected function configure(){
    $this
      ->setName('ctsearch:refactoring')
      ->setDescription('Refactoring elements')
      ->addOption('drop-analyzers', null, InputOption::VALUE_REQUIRED)
      ->addOption('drop-filters', null, InputOption::VALUE_REQUIRED)
      ->addOption('drop-fields', null, InputOption::VALUE_REQUIRED)
      ->addArgument('type', InputArgument::REQUIRED, 'Refactoring type : [Index, Mapping]')
      ->addArgument('source', InputArgument::REQUIRED, 'Source')
      ->addArgument('target', InputArgument::OPTIONAL, 'Target')
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
      if($target == null){
        $output->writeln('Target index is required');
        return;
      }
      $this->refactorIndex($source, $target, $input->getOption('drop-analyzers'), $input->getOption('drop-filters'));
    }
    if($type == 'mapping') {
      $output->writeln('Refactoring a mapping will cause the destruction of all data in the index.');
      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion('Continue with refactoring? (y/N) ', false);
      if (!$helper->ask($input, $output, $question)) {
        return;
      }
      $this->refactorMapping($source, $target, $input->getOption('drop-fields'));
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
  private function refactorMapping($source, $target, $dropField = NULL) {
    if(strpos($source, ".") === 0) {
      $indexName = '.' . explode(".", $source) [1];
      if(count(explode(".", $source)) == 3) {
        $mapping = explode(".", $source) [2];
      }
    }
    else {
      $indexName = explode(".", $source) [0];
      if(count(explode(".", $source)) == 2) {
        $mapping = explode(".", $source) [1];
      }
    }
    if(isset($mapping)) {
      $index = IndexManager::getInstance()->getIndex($indexName);
      if($index != null){
        $settings = json_decode($index->getSettings(), true);
        $mappings = [];
        $infos = IndexManager::getInstance()->getElasticInfo(false);
        foreach($infos as $i => $data) {
          if($i == $indexName){
            foreach($data['mappings'] as $mappingInfo){
              $mappings[] = IndexManager::getInstance()->getMapping($indexName, $mappingInfo['name']);
            }
          }
        }
        $this->output->write('Deleting index... ');
        IndexManager::getInstance()->deleteIndex($index);
        $this->output->writeln('Done!');
        $this->output->write('Creating index... ');
        IndexManager::getInstance()->createIndex($index);
        $this->output->writeln('Done!');
        foreach($mappings as $mapping) {
          /** @var Mapping $mapping */
          $this->output->write('Creating mapping ' . $mapping->getMappingName() . '... ');
          if($dropField != NULL) {
            $dropFields = array_map('trim', explode(',', $dropField));
            $fields = json_decode($mapping->getMappingDefinition(), true);
            foreach($dropFields as $field) {
              if(isset($fields[$field])) {
                unset($fields[$field]);
              }
            }
            $mapping->setMappingDefinition(json_encode($fields));
          }
          IndexManager::getInstance()->updateMapping($mapping);
          $this->output->writeln('Done!');
        }
      }
      else{
        $this->output->writeln('Source index does not exist');
      }
    }
  }

}