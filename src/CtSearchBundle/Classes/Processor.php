<?php

namespace CtSearchBundle\Classes;

class Processor implements Exportable, Importable
{

  /**
   * @var string
   */
  private $id;

  /**
   *
   * @var string
   */
  private $datasourceId;
  /**
   *
   * @var string
   */
  private $target;
  /**
   * @var array
   */
  private $targetSiblings;
  /**
   *
   * @var array
   */
  private $definition;

  function __construct($id = null, $datasourceId = null, $target = '', $definition = array(), $targetSiblings = array())
  {
    $this->id = $id;
    $this->datasourceId = $datasourceId;
    $this->target = $target;
    $this->definition = $definition;
    $this->targetSiblings = $targetSiblings;
  }

  public function export()
  {
    $datasource = IndexManager::getInstance()->getDatasource($this->getDatasourceId(), null);
    $index = IndexManager::getInstance()->getIndex(explode('.', $this->getTarget())[0]);
    $mapping = IndexManager::getInstance()->getMapping($index->getIndexName(), explode('.', $this->getTarget())[1]);
    $procDefinition = json_decode($this->getDefinition(), true);
    $matchingLists = array();
    foreach ($procDefinition['filters'] as $filter) {
      if ($filter['class'] == 'CtSearchBundle\Processor\MatchingListFilter') {
        $matchingList = IndexManager::getInstance()->getMatchingList($filter['settings']['matching_list']);
        $matchingLists[] = array(
          'id' => $matchingList->getId(),
          'name' => $matchingList->getName(),
          'list' => $matchingList->getList(),
        );
      }
    }
    $export = array(
      'id' => $this->getId(),
      'type' => 'processor',
      'index' => array(
        'name' => explode('.', $this->getTarget())[0],
        'settings' => json_decode($index->getSettings(), true)
      ),
      'mapping' => array(
        'name' => $mapping->getMappingName(),
        'definition' => json_decode($mapping->getMappingDefinition(), true)
      ),
      'datasource' => array(
        'class' => get_class($datasource),
        'id' => $datasource->getId(),
        'name' => $datasource->getName(),
        'has_batch_execution' => $datasource->isHasBatchExecution() ? 1 : 0,
        'settings' => $datasource->getSettings()
      ),
      'matching_lists' => $matchingLists,
      'processor_definition' => $procDefinition
    );
    if(count($this->getTargetSiblings()) > 0){
      foreach($this->getTargetSiblings() as $sibling){
        $siblingDS = IndexManager::getInstance()->getDatasource($sibling, null);
        $export['siblings'][] = array(
          'class' => get_class($siblingDS),
          'id' => $siblingDS->getId(),
          'name' => $siblingDS->getName(),
          'has_batch_execution' => $siblingDS->isHasBatchExecution() ? 1 : 0,
          'settings' => $siblingDS->getSettings()
        );
      }
    }
    if ($mapping->getDynamicTemplates() != NULL) {
      $export['mapping']['dynamic_templates'] = json_decode($mapping->getDynamicTemplates(), true);
    }
    return json_encode($export, JSON_PRETTY_PRINT);
  }

  public static function import($data, $override = false)
  {
    $serverVersion = IndexManager::getInstance()->getServerMajorVersionNumber();
    $indexExists = IndexManager::getInstance()->getIndex($data['index']['name']) != null;
    $settings = $data['index']['settings'];
    if($serverVersion >= 5 && isset($settings['legacy'])){
      unset($settings['legacy']);
    }
    $settings = json_encode($settings);
    if ($indexExists && $override) {
      $index = new \CtSearchBundle\Classes\Index($data['index']['name'], $settings);
      IndexManager::getInstance()->deleteIndex($index);
      IndexManager::getInstance()->createIndex($index);
    } elseif(!$indexExists) {
      IndexManager::getInstance()->createIndex(new \CtSearchBundle\Classes\Index($data['index']['name'], $settings));
    }

    $mapping = new \CtSearchBundle\Classes\Mapping($data['index']['name'], $data['mapping']['name'], json_encode($data['mapping']['definition']));
    if (isset($data['mapping']['dynamic_templates'])) {
      $mapping->setDynamicTemplates(json_encode($data['mapping']['dynamic_templates']));
    }
    if($serverVersion >= 5){
      $def = json_decode($mapping->getMappingDefinition(), TRUE);
      foreach($def as $field => $field_def){
        if($field_def['type'] == 'string'){
          if(isset($field_def['analyzer'])){
            $field_def['type'] = 'text';
          }
          else{
            $field_def['type'] = 'keyword';
            if(isset($field_def['boost'])){
              unset($field_def['boost']);
            }
            if(isset($field_def['index'])){
              unset($field_def['index']);
            }
          }
        }
        $def[$field] = $field_def;
      }
      $mapping->setMappingDefinition(json_encode($def));
    }
    IndexManager::getInstance()->updateMapping($mapping);

    $datasource = new $data['datasource']['class']($data['datasource']['name'], null);
    $datasource->initFromSettings($data['datasource']['settings']);
    $datasource->setId($data['datasource']['id']);
    $datasource->setHasBatchExecution($data['datasource']['has_batch_execution']);
    IndexManager::getInstance()->saveDatasource($datasource, $data['datasource']['id']);

    foreach ($data['matching_lists'] as $matchingList) {
      $list = new \CtSearchBundle\Classes\MatchingList($matchingList['name'], json_encode($matchingList['list']), $matchingList['id']);
      IndexManager::getInstance()->saveMatchingList($list);
    }
    $siblings = isset($data['siblings']) ? $data['siblings'] : array();
    $siblingsIds = array();
    foreach($siblings as $sibling){
      $datasource = new $sibling['class']($sibling['name'], null);
      $datasource->initFromSettings($sibling['settings']);
      $datasource->setId($sibling['id']);
      $datasource->setHasBatchExecution($sibling['has_batch_execution']);
      IndexManager::getInstance()->saveDatasource($datasource, $sibling['id']);
      $siblingsIds[] = $sibling['id'];
    }
    $processor = new Processor($data['id'], $data['datasource']['id'], $data['index']['name'] . '.' . $data['mapping']['name'], json_encode($data['processor_definition']), $siblingsIds);
    IndexManager::getInstance()->saveProcessor($processor, $data['id']);
  }

  /**
   * @return string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param string $id
   */
  public function setId($id)
  {
    $this->id = $id;
  }

  function getDatasourceId()
  {
    return $this->datasourceId;
  }

  function getTarget()
  {
    return $this->target;
  }

  function getDefinition()
  {
    return $this->definition;
  }

  function setDatasourceId($datasourceId)
  {
    $this->datasourceId = $datasourceId;
  }

  function setTarget($target)
  {
    $this->target = $target;
  }

  function setDefinition($definition)
  {
    $this->definition = $definition;
  }

  /**
   * @return array
   */
  public function getTargetSiblings()
  {
    return $this->targetSiblings;
  }

  /**
   * @param array $targetSiblings
   */
  public function setTargetSiblings($targetSiblings)
  {
    $this->targetSiblings = $targetSiblings;
  }


}
