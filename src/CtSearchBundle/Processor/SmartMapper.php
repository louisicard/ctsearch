<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SmartMapper extends ProcessorFilter {
  
  public function getDisplayName() {
    return "Smart mapper";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_force_index', CheckboxType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('Force indexing all fields'),
      ))
        ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }
  
  
  public function getFields() {
    return array('smart_array');
  }

  public function getArguments() {
    return array('source_array' => 'Source array');
  }

  public function execute(&$document) {
    return array('smart_array' => $this->getArgumentValue('source_array', $document));
  }

}
