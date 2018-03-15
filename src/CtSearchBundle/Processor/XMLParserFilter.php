<?php
namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class XMLParserFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "XML Parser";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
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
      $xml = $this->getArgumentValue('xml', $document);
      if(file_exists($xml)){
        $xml = file_get_contents($xml);
      }
      $doc = new \DOMDocument();
      $doc->loadXML($xml);
      
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
