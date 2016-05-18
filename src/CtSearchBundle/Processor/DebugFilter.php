<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class DebugFilter extends ProcessorFilter {
  
  public function getDisplayName() {
    return "Debug filter";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('setting_fields_to_dump', TextType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('Fields to dump'),
      ))
      ->add('setting_no_index', CheckboxType::class, array(
        'required' => false,
        'label' => $controller->get('translator')->trans('Prevent indexing'),
      ))
        ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }
  
  
  public function getFields() {
    return array();
  }
  
  public function getArguments(){
    return array();
  }
  
  public function execute(&$document) {
    $settings = $this->getSettings();

    if(isset($settings['fields_to_dump'])){
      $fields = explode(',', $settings['fields_to_dump']);
      print PHP_EOL;
      print '####################################################' . PHP_EOL;
      foreach($fields as $field){
        if(isset($document[$field])){
          print 'FIELD: ' . $field . PHP_EOL;
          print_r($document[$field]);
          print PHP_EOL;
          print '----------------------------------------------------' . PHP_EOL;
        }
      }
      print '####################################################' . PHP_EOL;
      print PHP_EOL;
    }

    if(isset($settings['no_index']) && $settings['no_index']){
      $document = array();
    }
    return array();
  }

}
