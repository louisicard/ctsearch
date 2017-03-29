<?php
namespace CtSearchBundle\Processor;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class SimpleXMLParserFilter extends ProcessorFilter {
  
  
  public function getDisplayName() {
    return "Simple XML Parser";
  }

  public function getSettingsForm($controller) {
    $formBuilder = parent::getSettingsForm($controller)
      ->add('ok', SubmitType::class, array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('doc');
  }

  public function getArguments() {
    return array(
      'xml' => 'XML source',
    );
  }
  
  public function execute(&$document) {
    try{
      return array('doc' => simplexml_load_string($this->getArgumentValue('xml', $document)));
    }catch(\Exception $ex){
      return array('doc' => null);
    }
  }
  
}
