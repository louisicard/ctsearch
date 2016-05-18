<?php

namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class AssociativeArraySelectorFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Associative array selector";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_key', TextType::class, array(
          'required' => true,
          'label' => $controller->get('translator')->trans('Key'),
        ))
        ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('value');
  }

  public function getArguments() {
    return array('array' => 'Input array');
  }

  public function execute(&$document) {
    $settings = $this->getSettings();
    $array = $this->getArgumentValue('array', $document);
    if(strpos($settings['key'], '##') === FALSE){
      if ($array != null && is_array($array) && isset($array[$settings['key']])) {
        return array('value' => $array[$settings['key']]);
      }
    }
    else{
      $keys = explode('##', $settings['key']);
      for($i = 0; $i < count($keys); $i++){
        if($i == 0){
          if(isset($array[$keys[$i]])){
            $tmp = $array[$keys[$i]];
          }
        }
        else{
          if(isset($tmp[$keys[$i]])){
            $tmp = $tmp[$keys[$i]];
          }
        }
      }
      if(isset($tmp))
        return array('value' => $tmp);
    }
    return array('value' => null);
  }

}
