<?php

namespace CtSearchBundle\Classes;

class Processor {
  
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
  
  function __construct($datasourceId = null, $target = '', $definition = array(), $targetSiblings = array()) {
    $this->datasourceId = $datasourceId;
    $this->target = $target;
    $this->definition = $definition;
    $this->targetSiblings = $targetSiblings;
  }
  function getDatasourceId() {
    return $this->datasourceId;
  }

  function getTarget() {
    return $this->target;
  }

  function getDefinition() {
    return $this->definition;
  }

  function setDatasourceId($datasourceId) {
    $this->datasourceId = $datasourceId;
  }

  function setTarget($target) {
    $this->target = $target;
  }

  function setDefinition($definition) {
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
