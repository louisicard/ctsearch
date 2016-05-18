<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ArrayImplodeFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Array implode";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_separator', TextType::class, array(
          'required' => true,
          'trim' => false,
          'label' => $controller->get('translator')->trans('Separator'),
        ))
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('string');
  }

  public function getArguments() {
    return array(
      'array' => 'Array to implde',
    );
  }
  
  public function execute(&$document) {
    $array = $this->getArgumentValue('array', $document);
    $settings = $this->getSettings();
    $separator = isset($settings['separator']) ? $settings['separator'] : '';
    return array('string' => implode($separator, $array));
  }

}
