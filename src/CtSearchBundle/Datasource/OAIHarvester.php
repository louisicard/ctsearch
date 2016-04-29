<?php

namespace CtSearchBundle\Datasource;

use Symfony\Component\Validator\Constraints as Assert;
use \CtSearchBundle\CtSearchBundle;

class OAIHarvester extends Datasource {

  private $oaiServerUrl;
  private $sets;
  private $metaDataPrefix;
  private $cookies = '';

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
    if (count($sets) > 0) {
      foreach ($sets as $set) {
        $count += $this->harvest($set);
      }
    } else {
      $this->harvest(NULL);
    }
    if ($this->getController() != null) {
      CtSearchBundle::addSessionMessage($this->getController(), 'status', 'Found ' . $count . ' documents');
    }
  }
  
  public function runCli($token){
    $sets = array_map('trim', explode(',', $this->getSets()));
    if (count($sets) > 0) {
      foreach ($sets as $set) {
        $this->harvest($set, $token, 0, true);
      }
    } else {
      $this->harvest(NULL, $token, 0, true);
    }
  }

  private function harvest($set, $resumptionToken = null, $count = 0, $cli = false) {
    $doc = new \DOMDocument();
    if ($resumptionToken == null)
      $url = $this->getOaiServerUrl() . '?verb=ListRecords&metadataPrefix=' . $this->getMetaDataPrefix() . ($set != NULL ? '&set=' . $set : '');
    else
      $url = $this->getOaiServerUrl() . '?verb=ListRecords&resumptionToken=' . urlencode($resumptionToken);
    if ($this->getOutput() != null) {
      $this->getOutput()->writeln('Harvesting url ' . $url);
    }
    $content = $this->getContentFromUrl($url);
    $config = array(
      'indent'     => true,
      'input-xml'  => true,
      'output-xml' => true,
      'wrap'       => false,
      'output-encoding' => 'utf8',
      'numeric-entities' => true,
      'preserve-entities' => true,
      'quote-ampersand' => true,
    );
    if(isset($content['encoding'])){
      if($content['encoding'] == 'utf-8' || $content['encoding'] == 'utf8'){
        $config['input-encoding'] = 'utf8';
      }
    }
    $tidy = @tidy_parse_string($content['content'], $config);
    tidy_clean_repair($tidy);
    $string = (string)$tidy;
    //Fixing unclean entities
    $string = str_replace("&#", "__CL_AMP__", $string);
    $string = str_replace("&", "&#38;", $string);
    $string = str_replace("__CL_AMP__", "&#", $string);
    //End fixing
    $doc->loadXML($string);
    $xpath = new \DOMXPath($doc);
    $result = $xpath->query("//namespace::*");

    foreach ($result as $node) {
      if ($node->nodeName == 'xmlns') {
        $xpath->registerNamespace('oai', $node->nodeValue);
      }
    }
    $items = $xpath->query('oai:ListRecords/oai:record');
    foreach ($items as $item) {
      $document = array();
      if ($xpath->query('oai:header/oai:identifier', $item)->length > 0)
        $document['identifier'] = $xpath->query('oai:header/oai:identifier', $item)->item(0)->textContent;
      if ($xpath->query('oai:header/oai:datestamp', $item)->length > 0)
        $document['datestamp'] = $xpath->query('oai:header/oai:datestamp', $item)->item(0)->textContent;
      if ($xpath->query('oai:header/oai:setSpec', $item)->length > 0){
        foreach($xpath->query('oai:header/oai:setSpec', $item) as $setSpec){
          $document['sets'][] = $setSpec->textContent;
        }
      }
      if ($xpath->query('oai:metadata/*', $item)->length > 0)
        $document['metadata'] = '<?xml version="1.0" encoding="' . $doc->encoding . '"?>' . simplexml_import_dom($xpath->query('oai:metadata/*', $item)->item(0))->asXML();

      if ($this->getOutput() != null) {
        $this->getOutput()->writeln(($count + 1) . ' / Harvesting doc "' . $document['identifier'] . '"');
        $this->getOutput()->writeln(sprintf('Memory usage (currently) %dKB/ (max) %dKB', round(memory_get_usage(true) / 1024), memory_get_peak_usage(true) / 1024));
      }

      $this->index($document);
      unset($document);
      $count ++;
    }
    unset($items);
    if (isset($item))
      unset($item);
    if ($xpath->query('oai:ListRecords/oai:resumptionToken')->length > 0 && !empty($xpath->query('oai:ListRecords/oai:resumptionToken')->item(0)->textContent)) {
      $token = $xpath->query('oai:ListRecords/oai:resumptionToken')->item(0)->textContent;
      unset($result);
      unset($xpath);
      unset($doc);
      gc_enable();
      gc_collect_cycles();
      if(!$cli){
        $this->harvest($set, $token, $count);
      }
      else{
        $this->getOutput()->writeln($token);
        exit(9);
      }
    }
    if($cli){
      exit(0);
    }
    return $count;
  }

  private function getContentFromUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if(!empty($this->cookies)){
      curl_setopt($ch, CURLOPT_COOKIE, $this->cookies);
    }
    $r = curl_exec($ch);
    $response = $this->parseHttpResponse($r);
    if(isset($response['headers']['set-cookie'])){
      $this->cookies = $response['headers']['set-cookie'];
    }
    preg_match_all('!\<\?xml.*encoding="(?<encoding>[^"]*)!', substr(strtolower($response['content']), 0, 300), $matches);
    if(isset($matches['encoding']) && !empty($matches['encoding'])){
      $response['encoding'] = $matches['encoding'][0];
    }
    $response['content'] = $this->cleanUTF8String($response['content']);
    return $response;
  }
  
  private function cleanUTF8String($str){
    return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $str);
  }

  private function parseHttpResponse($string) {

    $headers = array();
    $content = '';
    $str = strtok($string, "\n");
    $h = null;
    while ($str !== false) {
      if ($h and trim($str) === '') {
        $h = false;
        continue;
      }
      if ($h !== false and false !== strpos($str, ':')) {
        $h = true;
        list($headername, $headervalue) = explode(':', trim($str), 2);
        $headername = strtolower($headername);
        $headervalue = ltrim($headervalue);
        if (isset($headers[$headername]))
          $headers[$headername] .= ',' . $headervalue;
        else
          $headers[$headername] = $headervalue;
      }
      if ($h === false) {
        $content .= $str . "\n";
      }
      $str = strtok("\n");
    }
    return array('headers' => $headers, 'content' => trim($content));
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
      'sets',
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
