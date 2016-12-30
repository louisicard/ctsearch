<?php

namespace CtSearchBundle\Classes;

class Mapping {

  private $indexName;
  private $mappingName;
  private $mappingDefinition;
  private $dynamicTemplates;
  private $wipeData;

  function __construct($indexName, $mappingName, $mappingDefinition = '{}', $dynamicTemplates = NULL, $wipeData = false) {
    $this->indexName = $indexName;
    $this->mappingName = $mappingName;
    $this->mappingDefinition = $mappingDefinition;
    $this->dynamicTemplates = $dynamicTemplates;
    $this->wipeData = $wipeData;
  }
  function getIndexName() {
    return $this->indexName;
  }

  function getMappingName() {
    return $this->mappingName;
  }

  function getMappingDefinition() {
    return $this->mappingDefinition;
  }

  function setIndexName($indexName) {
    $this->indexName = $indexName;
  }

  function setMappingName($mappingName) {
    $this->mappingName = $mappingName;
  }

  function setMappingDefinition($mappingDefinition) {
    $this->mappingDefinition = $mappingDefinition;
  }

  public function getDynamicTemplates()
  {
    return $this->dynamicTemplates;
  }

  public function setDynamicTemplates($dynamicTemplates)
  {
    $this->dynamicTemplates = $dynamicTemplates;
  }
  
  function getWipeData() {
    return $this->wipeData;
  }

  function setWipeData($wipeData) {
    $this->wipeData = $wipeData;
  }
}
