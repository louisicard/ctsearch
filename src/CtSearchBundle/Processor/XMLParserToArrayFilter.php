<?php

namespace CtSearchBundle\Processor;

class XMLParserToArrayFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "XML Parser to array";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('array');
  }

  public function getArguments() {
    return array(
      'xml' => 'XML source',
    );
  }

  public function execute(&$document) {
    try {
      $xml = simplexml_load_string($this->getArgumentValue('xml', $document));
      
      $array = $this->serializeXml($xml);

      return array('array' => $array);
    } catch (\Exception $ex) {
      return array('array' => array());
    }
  }
  
  private function serializeXml(\SimpleXMLElement $xml){
    $r = array();
    foreach($xml->children() as $child){
      foreach($child->attributes() as $attr){
        $val = array();
        $val['@attributes'][$attr->getName()] = (string)$attr;
      }
      if($child->children()->count() === 0){
        $val['@value'] = (string)$child;
      }
      else{
        $val = $this->serializeXml($child);
      }
      if(isset($r[$child->getName()])){
        if(array_keys($r[$child->getName()])[0] !== 0){
          $r[$child->getName()] = array($r[$child->getName()]);
        }
        $r[$child->getName()][] = $val;
      }
      else{
        $r[$child->getName()] = $val;
      }
    }
    
    return $r;
  }

}
