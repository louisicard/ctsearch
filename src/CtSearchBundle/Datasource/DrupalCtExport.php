<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;

class DrupalCtExport extends Datasource {

  private $drupalHost;
  private $contentType;

  public function getSettings() {
    return array(
      'drupalHost' => $this->getDrupalHost() != null ? $this->getDrupalHost() : '',
      'contentType' => $this->getContentType() != null ? $this->getContentType() : '',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    try {
      $count = 0;
      if(isset($execParams['xml'])){
        $xml = simplexml_load_string($execParams['xml']);
        $this->processXML($xml, $count);
      }
      else{
        $url = 'http://' . $this->getDrupalHost() . '/ct/export';
        if($this->getContentType() != null && strlen($this->getContentType()) > 0){
          $url .= '?type=' . $this->getContentType();
          $url_sep = '&';
        }
        else{
          $url_sep = '?';
        }
        if ($this->getOutput() != null) {
          $this->getOutput()->writeln('Harvesting url ' . $url);
        }
        $xml = simplexml_load_file($url);
        $page = 1;
        while(count($xml->xpath('/nodes/node')) > 0){
          $this->processXML($xml, $count);
          $page++;
          $xml = simplexml_load_file($url . $url_sep . 'page=' . $page);
          if ($this->getOutput() != null) {
            $this->getOutput()->writeln('Harvesting url ' . $url . $url_sep . 'page=' . $page);
          }
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }

    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Found ' . $count . ' documents');
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
  }
  
  /**
   * 
   * @param \SimpleXMLElement $xml
   */
  private function processXML($xml, &$count){
    foreach ($xml->xpath('/nodes/node') as $node) {
      /* @var $node \SimpleXMLElement */
      $nid = count($node->xpath('@nid')) > 0 ? (string)$node->xpath('@nid')[0] : null;
      $export_id = count($node->xpath('export-id')) > 0 ? (string)$node->xpath('export-id')[0] : null;
      if($nid != null && $export_id != null){
        if ($this->getOutput() != null) {
          $this->getOutput()->writeln(($count + 1) . '/ Indexing ' . $export_id . ' ==> Type = ' . (string)$node->xpath('type')[0]);
        }
        $this->index(array(
          'nid' => $nid,
          'export_id' => $export_id,
          'xml' => simplexml_load_string($node->asXML()),
        ));
        $count++;
      }
    }
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('drupalHost', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Drupal Host'),
            'required' => true
          ))
          ->add('contentType', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Content type name'),
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
    return 'Drupal Ct Export';
  }

  public function getFields() {
    return array(
      'nid',
      'export_id',
      'xml',
    );
  }

  function getDrupalHost() {
    return $this->drupalHost;
  }

  function getContentType() {
    return $this->contentType;
  }

  function setDrupalHost($drupalHost) {
    $this->drupalHost = $drupalHost;
  }

  function setContentType($contentType) {
    $this->contentType = $contentType;
  }

}
