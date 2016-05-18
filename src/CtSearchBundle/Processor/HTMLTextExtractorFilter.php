<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class HTMLTextExtractorFilter extends ProcessorFilter {
  
  public function getDisplayName() {
    return "HTML text extractor";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }
  
  
  public function getFields() {
    return array('output');
  }
  
  public function getArguments(){
    return array('html_source' => 'HTML source');
  }
  
  public function execute(&$document) {
    $html = $this->getArgumentValue('html_source', $document);
    try{
      $tidy = tidy_parse_string($html, array(), 'utf8');
      $body = tidy_get_body($tidy);
      $html = $body->value;
    } catch (Exception $ex) {

    }
    $html = html_entity_decode($html, ENT_COMPAT | ENT_HTML401, 'utf-8');
    $output = html_entity_decode(trim(str_replace('&nbsp;', ' ', htmlentities(preg_replace('!\s+!', ' ', trim(preg_replace('#<[^>]+>#', ' ',$html))), null, 'utf-8'))));
    return array('output' => $output);
  }

}
