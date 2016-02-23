<?php

namespace CtSearchBundle\Datasource;

use \CtSearchBundle\CtSearchBundle;

class XMLParser extends Datasource {

  private $url;
  private $xpath;

  public function getSettings() {
    return array(
      'url' => $this->getUrl() != null ? $this->getUrl() : '',
      'xpath' => $this->getXpath() != null ? $this->getXpath() : '',
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
      if(isset($execParams['file']) && !empty($execParams['file'])){
        $file = $execParams['file'];
        /* @var $file \Symfony\Component\HttpFoundation\File\File */
        $str = file_get_contents($file->getRealPath());
        $xml = simplexml_load_string($str);
      }
      elseif(isset($this->getSettings()['url']) && !empty($this->getSettings()['url'])){
        $xml = simplexml_load_file($this->getSettings()['url']);
      }
      if(isset($xml)){
        $docs = $xml->xpath($this->getXpath());
        foreach($docs as $doc){
          $this->index(array(
            'doc' => simplexml_load_string($doc->asXML())
          ));
          $count++;
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

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('url', 'text', array(
            'label' => $this->getController()->get('translator')->trans('XML File url'),
            'required' => false
          ))
          ->add('xpath', 'text', array(
            'label' => $this->getController()->get('translator')->trans('XPath'),
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
        ->add('file', 'file', array(
          'label' => $this->getController()->get('translator')->trans('File'),
          'required' => false
        ))
        ->add('ok', 'submit', array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'XML Parser';
  }

  public function getFields() {
    return array(
      'doc',
    );
  }
  function getUrl() {
    return $this->url;
  }

  function getXpath() {
    return $this->xpath;
  }

  function setUrl($url) {
    $this->url = $url;
  }

  function setXpath($xpath) {
    $this->xpath = $xpath;
  }



}
