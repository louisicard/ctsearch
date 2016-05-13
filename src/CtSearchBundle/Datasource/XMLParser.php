<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use \CtSearchBundle\CtSearchBundle;

class XMLParser extends Datasource {

  private $url;
  private $xpath;
  private $xpathNamespaces;

  public function getSettings() {
    return array(
      'url' => $this->getUrl() != null ? $this->getUrl() : '',
      'xpath' => $this->getXpath() != null ? $this->getXpath() : '',
      'xpathNamespaces' => $this->getXpathNamespaces() != null ? $this->getXpathNamespaces() : '',
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
        $url = $this->getSettings()['url'];
        if(strpos($url, 'http') === 0){
          $xml = simplexml_load_string($this->getContentFromUrl($url));
        }
        else {
          $xml = simplexml_load_file($url);
        }
      }
      if(isset($xml)){
        if(isset($this->getSettings()['xpathNamespaces']) && !empty($this->getSettings()['xpathNamespaces'])){
          $nss = explode(',', $this->getSettings()['xpathNamespaces']);
          foreach($nss as $ns){
            $prefix = substr($ns, 0, strpos($ns, ':'));
            $url = substr($ns, strpos($ns, ':') + 1);
            $xml->registerXpathNamespace($prefix , $url);
          }
        }
        $docs = $xml->xpath($this->getXpath());
        if ($this->getOutput() != null) {
          $this->getOutput()->writeln('Found ' . count($docs) . ' documents');
        }
        foreach($docs as $doc){
          foreach($xml->getNamespaces(true) as $prefix => $ns){
            if(!empty($prefix)){
              $doc->addAttribute($prefix . ':ctsearch', 'ctsearch', $prefix);
            }
          }
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
      $this->getOutput()->writeln('Processed ' . $count . ' documents');
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
  }

  private function getContentFromUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    CurlUtils::handleCurlProxy($ch);
    $r = curl_exec($ch);
    return $r;
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
          ->add('xpathNamespaces', 'text', array(
            'label' => $this->getController()->get('translator')->trans('XPath Namespaces to register'),
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

  function getXpathNamespaces() {
    return $this->xpathNamespaces;
  }

  function setXpathNamespaces($xpathNamespaces) {
    $this->xpathNamespaces = $xpathNamespaces;
  }



}
