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
      $xmlDoc = simplexml_load_string(str_replace('xmlns=', 'ns=', $this->getArgumentValue('xml', $document)));
      foreach($xmlDoc->getDocNamespaces() as $strPrefix => $strNamespace) {
        //if(strlen($strPrefix) == 0) {
        //  $strPrefix = "a"; //Assign an arbitrary namespace prefix.
        //}
        $xmlDoc->registerXPathNamespace($strPrefix,$strNamespace);
      }

      return array('doc' => $xmlDoc);
    }catch(\Exception $ex){
      return array('doc' => null);
    }
  }
  
}
