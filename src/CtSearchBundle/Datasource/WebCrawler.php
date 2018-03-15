<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\CurlUtils;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints as Assert;
use \CtSearchBundle\CtSearchBundle;

class WebCrawler extends Datasource {

  public function getSettings() {
    return array();
  }

  public function execute($execParams = null) {

    parent::execute($execParams);
  }
  public function handleDataFromCallback($document){
    $this->index($document);
  }

  public function getSettingsForm() {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $formBuilder
        ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Save')));
      return $formBuilder;
    } else {
      return null;
    }
  }

  public function getExcutionForm() {
    $formBuilder = $this->getController()->createFormBuilder()
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getFields() {
    return array(
      'title',
      'html',
      'url',
    );
  }

  public function getDatasourceDisplayName() {
    return 'Web crawler';
  }

}
