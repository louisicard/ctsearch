<?php
namespace CtSearchBundle\Processor;

class XPathGetterFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "XPath Getter (SimpleXml)";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_xpath', 'text', array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Xpath'),
        ))
      ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
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
      
      $r = $xml->xpath($settings['xpath']);
      
      if(count($r) == 1 && strlen(trim((string)$r[0])) > 0){
        return array('value' => trim((string)$r[0]));
      }
      elseif(count($r) > 1){
        $vals = array();
        foreach($r as $val){
          if(strlen(trim((string)$val)) > 0 && !in_array(trim((string)$val), $vals)){
            $vals[] = trim((string)$val);
          }
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
  
}
