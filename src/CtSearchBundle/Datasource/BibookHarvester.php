<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;

class BibokHarverster extends Datasource {

  private $url;

  public function getSettings() {
    return array(
      'url' => $this->getUrl() != null ? $this->getUrl() : '',
    );
  }

  public function initFromSettings($settings) {
    foreach ($settings as $k => $v) {
      $this->{$k} = $v;
    }
  }

  public function execute($execParams = null) {
    try {
      if(isset($this->getSettings()['url']) && !empty($this->getSettings()['url'])){
        $page = 1;
        $stop = false;
        $count = 1;
        while(!$stop){
          $url = $this->getSettings()['url'] . '?page=' . $page;
          if ($this->getOutput() != null) {
            $this->getOutput()->writeln('Harvesting ' . $url);
          }
          $xml = simplexml_load_file($url);
          $page++;
          $docs = $xml->xpath('/resources/resource');
          $stop = count($docs) == 0;
          foreach($docs as $doc){
            if ($this->getOutput() != null) {
              $this->getOutput()->writeln('Indexing document ' . $count);
            }
            $this->index(array(
              'xml' => $doc
            ));
            $count++;
          }
        }
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }
    /*
    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Found ' . $count . ' documents');
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }*/
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('url', 'text', array(
            'label' => $this->getController()->get('translator')->trans('Bibook service url'),
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
    return 'Bibook Harvester';
  }

  public function getFields() {
    return array(
      'xml',
    );
  }
  function getUrl() {
    return $this->url;
  }

  function setUrl($url) {
    $this->url = $url;
  }

}
