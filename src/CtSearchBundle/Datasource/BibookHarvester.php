<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use \CtSearchBundle\CtSearchBundle;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

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
          $xml = simplexml_load_string($this->getContentFromUrl($url));
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
          unset($docs);
          unset($xml);
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
      $formBuilder->add('url', TextType::class, array(
            'label' => $this->getController()->get('translator')->trans('Bibook service url'),
            'required' => true
          ))
          ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
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
