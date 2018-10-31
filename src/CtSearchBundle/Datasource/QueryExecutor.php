<?php

namespace CtSearchBundle\Datasource;

use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class QueryExecutor extends Datasource
{

  protected $mapping;
  protected $query;

  public function getSettings()
  {
    return array(
      'mapping' => $this->getMapping() != null ? $this->getMapping() : '',
      'query' => $this->getQuery() != null ? $this->getQuery() : '',
    );
  }

  public function execute($execParams = null)
  {
    $index = strpos($this->getMapping(), '.') !== 0 ? explode('.', $this->getMapping())[0] : '.' . explode('.', $this->getMapping())[1];
    $mapping = strpos($this->getMapping(), '.') !== 0 ? explode('.', $this->getMapping())[1] : explode('.', $this->getMapping())[2];
    $this->execSearch($index, $mapping);
    parent::execute($execParams);
  }

  private function execSearch($index, $mapping, $from = 0) {
    $size = 100;
    $res = IndexManager::getInstance()->search($index, $this->getQuery(), $from, $size, $mapping);
    if(isset($res['hits']['total'])) {
      $total = $res['hits']['total'];
      if(isset($res['hits']['hits'])) {
        foreach($res['hits']['hits'] as $hit) {
          $id = $hit['_id'];
          $doc = $hit['_source'];
          $this->index(array(
            'id' => $id,
            'doc' => $doc
          ));
        }
      }
      if($from < $total) {
        $this->execSearch($index, $mapping, $from + $size);
      }
    }
  }

  public function getSettingsForm()
  {
    if ($this->getController() != null) {
      $formBuilder = parent::getSettingsForm();
      $indexes = IndexManager::getInstance()->getElasticInfo($this);
      $targetChoices = array();
      foreach ($indexes as $indexName => $info) {
        $choices = array();
        if (isset($info['mappings'])) {
          foreach ($info['mappings'] as $mapping) {
            $choices[$indexName . '.' . $mapping['name']] = $indexName . '.' . $mapping['name'];
          }
        }
        $targetChoices[$indexName] = $choices;
      }
      ksort($targetChoices);
      $formBuilder->add('mapping', ChoiceType::class, array(
        'label' => $this->getController()->get('translator')->trans('Mapping'),
        'choices' => $targetChoices,
        'required' => true
      ))
        ->add('query', TextareaType::class, array(
        'label' => $this->getController()->get('translator')->trans('Query (JSON)'),
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
      ->add('ok', SubmitType::class, array('label' => $this->getController()->get('translator')->trans('Execute')));
    return $formBuilder;
  }

  public function getFields()
  {
    return array('id', 'doc');
  }

  public function getDatasourceDisplayName()
  {
    return 'Query Executor';
  }

  /**
   * @return mixed
   */
  public function getMapping()
  {
    return $this->mapping;
  }

  /**
   * @param mixed $mapping
   */
  public function setMapping($mapping)
  {
    $this->mapping = $mapping;
  }

  /**
   * @return mixed
   */
  public function getQuery()
  {
    return $this->query;
  }

  /**
   * @param mixed $query
   */
  public function setQuery($query)
  {
    $this->query = $query;
  }


}
