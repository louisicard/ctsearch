<?php

namespace CtSearchBundle\Processor;

class ExistingDocumentFilter extends ProcessorFilter {

  public function getDisplayName() {
    return "Existing document finder";
  }

  public function getSettingsForm($controller) {
    $indexManager = $this->getIndexManager();
    $infos = $indexManager->getElasticInfo();
    $choices = array();
    foreach(array_keys($infos) as $index_name){
      $choices[$index_name] = $index_name;
    }
    $formBuilder = parent::getSettingsForm($controller)
        ->add('setting_index_name', 'choice', array(
          'required' => true,
          'choices' => array('' => $controller->get('translator')->trans('Select')) + $choices,
          'label' => $controller->get('translator')->trans('Index name'),
        ))
        ->add('ok', 'submit', array('label' => $controller->get('translator')->trans('OK')));
    return $formBuilder;
  }

  public function getFields() {
    return array('doc');
  }

  public function getArguments() {
    return array(
      'doc_id' => 'Document ID',
    );
  }

  public function execute(&$document) {
    try {
      $json = '{
          "query": {
              "ids": {"values":["' . $this->getArgumentValue('doc_id', $document) . '"]}
          }
      }';
      $res = $this->getIndexManager()->search($this->getSettings()['index_name'], $json);
      if(isset($res['hits']['hits'][0])){
        return array('doc' => $res['hits']['hits'][0]);
      }
    } catch (\Exception $ex) {
      var_dump($ex);
    }
    return array('doc' => NULL);
  }

}
