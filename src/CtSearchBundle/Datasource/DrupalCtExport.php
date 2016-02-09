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
      $url = 'http://' . $this->getDrupalHost() . '/ct/export?type=' . $this->getContentType();
      if ($this->getOutput() != null) {
        $this->getOutput()->writeln('Harvesting url ' . $url);
      }
      $xml = simplexml_load_file($url);
      foreach ($xml->xpath('/nodes/node') as $node) {
        /* @var $node \SimpleXMLElement */
        foreach ($node->attributes() as $attr) {
          if ($attr->getName() == 'nid') {
            $nid = (string) $attr . PHP_EOL;
            $this->index(array(
              'id' => trim($nid),
              'xml' => $node->asXML()
            ));
          }
        }
        $count++;
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

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('drupalHost', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Drupal Host'),
            'required' => true
          ))
          ->add('contentType', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Content type name'),
            'required' => true
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
      'id',
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
