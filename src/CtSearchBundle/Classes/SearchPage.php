<?php

namespace CtSearchBundle\Classes;

class SearchPage {
  
  private $id;
  private $name;
  private $indexName;
  private $definition;
  private $config;
  
  function __construct($name, $indexName, $definition, $config, $id = null) {
    $this->id = $id;
    $this->name = $name;
    $this->indexName = $indexName;
    $this->definition = $definition;
    $this->config = $config;
  }
  function getConfig() {
    return $this->config;
  }

  function setConfig($config) {
    $this->config = $config;
  }

    function getId() {
    return $this->id;
  }

  function setId($id) {
    $this->id = $id;
  }
  
  function getName() {
    return $this->name;
  }

  function getIndexName() {
    return $this->indexName;
  }

  function getDefinition() {
    return $this->definition;
  }

  function setName($name) {
    $this->name = $name;
  }

  function setIndexName($indexName) {
    $this->indexName = $indexName;
  }

  function setDefinition($definition) {
    $this->definition = $definition;
  }

}
