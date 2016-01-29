<?php
namespace CtSearchBundle\Processor;

class XMLParserFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "XML Parser";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('xpath');
  }

  public function getArguments() {
    return array(
      'xml' => 'XML source',
    );
  }
  
  public function execute(&$document) {
    try{
      $doc = new \DOMDocument();
      $doc->loadXML($this->getArgumentValue('xml', $document));
      
      $xpath = new \DOMXPath($doc);
      $result = $xpath->query("//namespace::*");
      foreach ($result as $node) {
       if($node->nodeName == 'xmlns'){
         $xpath->registerNamespace('vendor', $node->nodeValue);
       }
      }
      
      return array('xpath' => $xpath); 
    }catch(\Exception $ex){
      return array('xpath' => null);
    }
  }
  
}
