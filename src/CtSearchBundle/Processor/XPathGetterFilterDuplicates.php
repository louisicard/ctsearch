<?php
namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class XPathGetterFilterDuplicates extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "XPath Getter with duplicates (SimpleXml)";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_xpath', TextType::class, array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Xpath'),
        ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('value');
  }

  public function getArguments() {
    return array(
      'xml' => 'SimpleXml element',
    );
  }
  
  public function execute(&$document) {
    try{
      $settings = $this->getSettings();
      $xml = $this->getArgumentValue('xml', $document);
      /* @var $xml \SimpleXMLElement */
      if(get_class($xml) == 'SimpleXMLElement'){
        $r = $xml->xpath($settings['xpath']);
      }
      else{
        $r = array();
      }
      
      if(count($r) == 1 && strlen(trim($this->xmlToString($r[0]))) > 0){
        return array('value' => trim($this->xmlToString($r[0])));
      }
      elseif(count($r) > 1){
        $vals = array();
        foreach($r as $val){
         // if(strlen(trim($this->xmlToString($val))) > 0 && !in_array(trim($this->xmlToString($val)), $vals)){
            $vals[] = trim($this->xmlToString($val));
          //}
        }
        return array('value' => $vals);
      }
      else{
        return array('value' => null);
      }
    }catch(\Exception $ex){
      return array('value' => null);
    }
  }

  private function xmlToString(\SimpleXMLElement $elem) {
    $string = $elem->asXML();
    $openTag = '<' . $elem->getName() . '>';
    $closeTag = '</' . $elem->getName() . '>';
    if(strpos($string, $openTag) !== FALSE) {
      $string = substr($string, strlen($openTag), strlen($string) - strlen($closeTag) - strlen($openTag));
      if(!$string) $string = "";
      return trim($string);
    }
    else {
      return (string)$elem;
    }
  }
  
}
