<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;

class ToutApprendre extends Datasource {

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
        $count = 1;
        $url = $this->getSettings()['url'];
        if ($this->getOutput() != null) {
          $this->getOutput()->writeln('Harvesting ' . $url);
        }
        $xml = simplexml_load_file($url);
        $docs = $xml->xpath('/toutapprendre/cours');
        foreach($docs as $doc){
          if ($this->getOutput() != null) {
            $this->getOutput()->writeln('Indexing document ' . $count);
          }
          $this->index(array(
            'xml' => simplexml_load_string($doc->asXML())
          ));
          $count++;
        }
        unset($docs);
        unset($xml);
      }
    } catch (Exception $ex) {
      print $ex->getMessage();
    }
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
    return 'Moissonneur ToutApprendre';
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
