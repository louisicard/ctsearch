<?php

namespace CtSearchBundle\Classes;

class Index {
  
  private $indexName;
  private $settings;
  
  function __construct($indexName = '', $settings = '[]') {
    $this->indexName = $indexName;
    $this->settings = $settings;
  }

  function getIndexName() {
    return $this->indexName;
  }

  function getSettings() {
    return $this->settings;
  }

  function setIndexName($indexName) {
    $this->indexName = $indexName;
  }

  function setSettings($settings) {
    $this->settings = $settings;
  }


}
