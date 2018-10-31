<?php

namespace CtSearchBundle\Datasource;

use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class JSONParser extends Datasource
{

  protected $jsonFields;

  public function getSettings()
  {
    return array(
      'jsonFields' => $this->getJsonFields() != null ? $this->getJsonFields() : '',
    );
  }

  public function execute($execParams = null)
  {
    if ($execParams != null) {
      if (isset($execParams['json_file'])) {
        if(is_string($execParams['json_file'])){
          $arrContextOptions=array(
            "ssl"=>array(
              "verify_peer"=>false,
              "verify_peer_name"=>false,
            ),
          );
          $json = file_get_contents($execParams['json_file'], false, stream_context_create($arrContextOptions));
        }
        elseif ($execParams['json_file']->getMimeType() == 'application/json' || $execParams['json_file']->getMimeType() == 'text/plain') {
          $json = file_get_contents($execParams['json_file']->getRealPath());
        }
      }
    }
    if (!isset($json))
      $json = "[]";
    $data = json_decode($json, true);
    if ($data == null) {
      $data = array();
    }
    $r = array();
    $fields = array_map('trim', explode(',', $this->getJsonFields()));
    foreach ($data as $doc) {
      $tmp = array();
      foreach ($fields as $field) {
        if (isset($doc[$field]))
          $tmp[$field] = $doc[$field];
      }
      if (!empty($tmp))
        $r[] = $tmp;
    }
    $this->multiIndex($r);
    parent::execute($execParams);
  }

  public function getSettingsForm()
  {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder->add('jsonFields', TextType::class, array(
        'label' => $this->getController()->get('translator')->trans('JSON fields (comma separated)'),
        'required' => true
      ))
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm()
  {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('json_file', FileType::class, array(
        'label' => 'JSON file to import',
        'required' => true
      ))
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getFields()
  {
    return array_map('trim', explode(',', $this->getJsonFields()));
  }

  public function getDatasourceDisplayName()
  {
    return 'JSON parser';
  }

  function getJsonFields()
  {
    return $this->jsonFields;
  }

  function setJsonFields($jsonFields)
  {
    $this->jsonFields = $jsonFields;
  }


}
