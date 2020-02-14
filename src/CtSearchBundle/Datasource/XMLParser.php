<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use \CtSearch\ClientBundle\Classes\CurlClient;

class XMLParser extends Datasource {

  protected $url;
  protected $xpath;
  protected $xpathNamespaces;

  public function getSettings() {
    return array(
      'url' => $this->getUrl() != null ? $this->getUrl() : '',
      'xpath' => $this->getXpath() != null ? $this->getXpath() : '',
      'xpathNamespaces' => $this->getXpathNamespaces() != null ? $this->getXpathNamespaces() : '',
    );
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
          $curlClient = new CurlClient($url);
          $response = $curlClient->getResponse();

          if (!empty($response['data'])) {
            $xml = simplexml_load_string($response['data']);
          } else {
            $xml = false;
          }
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
            'global_doc' => $xml,
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
    parent::execute($execParams);
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
    curl_close($ch);
    return $r;
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('url', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('XML File url'),
            'required' => false
          ))
          ->add('xpath', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('XPath'),
            'required' => true
          ))
          ->add('xpathNamespaces', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('XPath Namespaces to register'),
            'required' => false
          ))
          ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
        ->add('file', FileType::class, array(
          'label' => $this->getController()->get('translator')->trans('File'),
          'required' => false
        ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getDatasourceDisplayName() {
    return 'XML Parser';
  }

  public function getFields() {
    return array(
      'global_doc',
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
