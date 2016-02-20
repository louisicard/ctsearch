<?php

namespace CtSearchBundle\Datasource;

use Symfony\Component\Validator\Constraints as Assert;
use \CtSearchBundle\CtSearchBundle;

class OAIHarvester extends Datasource {

  private $oaiServerUrl;
  private $sets;
  private $metaDataPrefix;

  public function getSettings() {
    return array(
      'oaiServerUrl' => $this->getOaiServerUrl() != null ? $this->getOaiServerUrl() : '',
      'sets' => $this->getSets() != null ? $this->getSets() : '',
      'metaDataPrefix' => $this->getMetaDataPrefix() != null ? $this->getMetaDataPrefix() : '',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    $sets = array_map('trim', explode(',', $this->getSets()));
    $count = 0;
    if(count($sets) > 0){
      foreach ($sets as $set) {
        $count += $this->harvest($set);
      }
    }
    else{
      $this->harvest(NULL);
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
  }

  private function harvest($set, $resumptionToken = null, $count = 0) {
    $doc = new \DOMDocument();
    if ($resumptionToken == null)
      $url = $this->getOaiServerUrl() . '?verb=ListRecords&metadataPrefix=' . $this->getMetaDataPrefix() . ($set != NULL ? '&set=' . $set : '');
    else
      $url = $this->getOaiServerUrl() . '?verb=ListRecords&resumptionToken=' . urlencode($resumptionToken);
    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Harvesting url ' . $url);
    }
    $doc->load($url);
    $xpath = new \DOMXPath($doc);
    $result = $xpath->query("//namespace::*");

    foreach ($result as $node) {
      if ($node->nodeName == 'xmlns') {
        $xpath->registerNamespace('oai', $node->nodeValue);
      }
    }
    $items = $xpath->query('oai:ListRecords/oai:record');
    foreach ($items as $index => $item) {
      $document = array();
      if ($xpath->query('oai:header/oai:identifier', $item)->length > 0)
        $document['identifier'] = $xpath->query('oai:header/oai:identifier', $item)->item(0)->textContent;
      if ($xpath->query('oai:header/oai:datestamp', $item)->length > 0)
        $document['datestamp'] = $xpath->query('oai:header/oai:datestamp', $item)->item(0)->textContent;
      if ($xpath->query('oai:metadata/*', $item)->length > 0)
        $document['metadata'] = '<?xml version="1.0" encoding="' . $doc->encoding . '"?>' . simplexml_import_dom($xpath->query('oai:metadata/*', $item)->item(0))->asXML();

      if ($this->getOutput() != null) {
        $this->getOutput()->writeln(($count + 1) . ' / Harvesting doc "' . $document['identifier'] . '"');
      }

      $this->index($document);
      unset($document);
      unset($items[$index]);
      $count ++;
    }
    if ($xpath->query('oai:ListRecords/oai:resumptionToken')->length > 0 && !empty($xpath->query('oai:ListRecords/oai:resumptionToken')->item(0)->textContent)) {
      $this->harvest($set, $xpath->query('oai:ListRecords/oai:resumptionToken')->item(0)->textContent, $count);
    }
    return $count;
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('oaiServerUrl', 'text', array(
            'label' => $this->getController()->get('translator')->trans('OAI server URL'),
            'required' => true
          ))
          ->add('sets', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Sets to harvest (comma separated)'),
            'required' => false
          ))
          ->add('metaDataPrefix', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Metadata prefix'),
            'required' => false
          ))
          ->add('ok', 'submit', array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
        ->add('ok', 'submit', array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'OAI Harvester';
  }

  public function getFields() {
    return array(
      'identifier',
      'datestamp',
      'metadata',
    );
  }

  function getOaiServerUrl() {
    return $this->oaiServerUrl;
  }

  function getSets() {
    return $this->sets;
  }

  function getMetaDataPrefix() {
    return $this->metaDataPrefix;
  }

  function setOaiServerUrl($oaiServerUrl) {
    $this->oaiServerUrl = $oaiServerUrl;
  }

  function setSets($sets) {
    $this->sets = $sets;
  }

  function setMetaDataPrefix($metaDataPrefix) {
    $this->metaDataPrefix = $metaDataPrefix;
  }

}
