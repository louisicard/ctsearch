<?php

namespace CtSearchBundle\Processor;

class PHPFilter extends ProcessorFilter {
  
  public function getDisplayName() {
    return "PHP filter";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_php_code', 'textarea', array(
          'required' => true,
          'label' => $controller->get('translator')->trans('PHP Code'),
        ))
        ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }
  
  
  public function getFields() {
    return array('return');
  }
  
  public function getArguments(){
    return array();
  }
  
  private function evalCode(&$document, $code){
    return eval($code);
  }
  
  public function execute(&$document) {
    $settings = $this->getSettings();
    if(isset($settings['php_code'])){
      try{
        $return = $this->evalCode($document, $settings['php_code']);
      } catch (Exception $ex) {
        $return  = '';
      }
    }
    return array('return' => $return);
  }

}
