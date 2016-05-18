<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DefineConstantFilter extends ProcessorFilter {
  
  public function getDisplayName() {
    return "Define constant value";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_value', TextType::class, array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Value'),
        ))
        ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
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
