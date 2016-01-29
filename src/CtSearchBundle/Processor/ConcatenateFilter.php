<?php

namespace CtSearchBundle\Processor;

class ConcatenateFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Concatenate";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_separator', 'text', array(
          'required' => true,
          'trim' => false,
          'label' => $controller->get('translator')->trans('Separator'),
        ))
      ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('result');
  }

  public function getArguments() {
    return array(
      'field_1' => 'Field 1',
      'field_2' => 'Field 2'
    );
  }
  
  public function execute(&$document) {
    $field1 = $this->getArgumentValue('field_1', $document);
    $field2 = $this->getArgumentValue('field_2', $document);
    $settings = $this->getSettings();
    $separator = isset($settings['separator']) ? $settings['separator'] : '';
    return array('result' => $field1 . $separator . $field2);
  }

}
