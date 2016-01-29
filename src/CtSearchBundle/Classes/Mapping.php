<?php

namespace CtSearchBundle\Classes;

class Mapping {

  private $indexName;
  private $mappingName;
  private $mappingDefinition;
  private $wipeData;

  function __construct($indexName, $mappingName, $mappingDefinition = '{}', $wipeData = false) {
    $this->indexName = $indexName;
    $this->mappingName = $mappingName;
    $this->mappingDefinition = $mappingDefinition;
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
  
  function getWipeData() {
    return $this->wipeData;
  }

  function setWipeData($wipeData) {
    $this->wipeData = $wipeData;
  }
}
