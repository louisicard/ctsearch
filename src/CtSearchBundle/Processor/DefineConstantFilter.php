<?php

namespace CtSearchBundle\Processor;

class DefineConstantFilter extends ProcessorFilter {
  
  public function getDisplayName() {
    return "Define constant value";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_value', 'text', array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Value'),
        ))
        ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }
  
  
  public function getFields() {
    return array('value');
  }
  
  public function getArguments(){
    return array();
  }
  
  public function execute(&$document) {
    $settings = $this->getSettings();
    return array('value' => $settings['value']);
  }

}
