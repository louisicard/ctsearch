<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class ConsoleController extends Controller {

  /**
   * @Route("/console", name="console")
   */
  public function consoleAction(Request $request) {
    $indexes = IndexManager::getInstance()->getElasticInfo();
    $targetChoices = array();
    foreach ($indexes as $indexName => $info) {
      $choices = array();
      if(isset($info['mappings'])){
        foreach ($info['mappings'] as $mapping) {
          $choices[$indexName . '.' . $mapping['name']] = $indexName . '.' . $mapping['name'];
        }
      }
      $targetChoices[$indexName] = $choices;
    }
    $listener = function(\Symfony\Component\Form\FormEvent $event) {
      $data = $event->getData();
      $data["searchQuery"] = json_encode(json_decode($data["searchQuery"]), JSON_PRETTY_PRINT);
      $event->setData($data);
    };
    if($request->get('id') != null){
      $savedQuery = IndexManager::getInstance()->getSavedQuery($request->get('id'));
    }
    $values = array(
      'mapping' => isset($savedQuery['target']) ? $savedQuery['target'] : null,
      'searchQuery' => isset($savedQuery['definition']) ? $savedQuery['definition'] : json_encode(array('query' => array('match_all' => new \stdClass())), JSON_PRETTY_PRINT),
      'deleteByQuery' => false,
    );
    $form = $this->createFormBuilder($values)
        ->add('mapping', 'choice', array(
          'label' => $this->get('translator')->trans('Target'),
          'choices' => array('' => $this->get('translator')->trans('Select a target')) + $targetChoices,
          'required' => true,
        ))
        ->add('searchQuery', 'textarea', array(
          'label' => $this->get('translator')->trans('Search query (JSON)'),
          'required' => true
        ))
        ->add('deleteByQuery', 'checkbox', array(
          'label' => $this->get('translator')->trans('Delete records matching this query'),
          'required' => false
        ))
        ->add('execute', 'submit', array('label' => $this->get('translator')->trans('Execute')))
        ->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT, $listener)
        ->getForm();

    $form->handleRequest($request);
    $params = array(
      'title' => $this->get('translator')->trans('Console'),
      'form' => $form->createView(),
      'main_menu_item' => 'console',
    );
    if ($form->isValid()) {
      $data = $form->getData();
      $query = $data['searchQuery'];
      $query_r = json_decode($data['searchQuery'], true);
      $index = strpos($data['mapping'], '.') !== 0 ? explode('.', $data['mapping'])[0] : '.' . explode('.', $data['mapping'])[1];
      $mapping = strpos($data['mapping'], '.') !== 0 ? explode('.', $data['mapping'])[1] : explode('.', $data['mapping'])[2];
      try {
        if (!$data['deleteByQuery']) {
          $res = IndexManager::getInstance()->search($index, $query, isset($query_r['from']) ? $query_r['from'] : 0, isset($query_r['size']) ? $query_r['size'] : 20);
          $params['results'] = $this->dumpVar($res);
          if(isset($res['aggregations']) && count($res['aggregations']) > 0){
            $params['facets'] = json_encode($res['aggregations'], JSON_PRETTY_PRINT);
          }
          $params['engine_response'] = $this->getFormattedEngineReponse($res);
          $saveUrlParams = array();
          if($request->get('id') != null){
            $saveUrlParams['id'] = $request->get('id');
          }
          $saveUrlParams['target'] = $data['mapping'];
          $saveUrlParams['query'] = $data['searchQuery'];
          $saveUrl = $this->generateUrl('console-save', $saveUrlParams);
          $params['save_url'] = $saveUrl;
          if(isset($saveUrlParams['id'])){
            unset($saveUrlParams['id']);
            $params['clone_url'] = $this->generateUrl('console-save', $saveUrlParams);
          }
        } else {
          IndexManager::getInstance()->deleteByQuery($index, $mapping, $query);
        }
      } catch (\Exception $ex) {
        $params['exception'] = $ex->getMessage() . ', Line ' . $ex->getLine() . ' in ' . $ex->getFile();
      }
    }
    return $this->render('ctsearch/console.html.twig', $params);
  }

  /**
   * @Route("/console/save", name="console-save")
   */
  public function saveQueryAction(Request $request) {
    $id = $request->get('id');
    $target = $request->get('target');
    $query = $request->get('query');
    $r = IndexManager::getInstance()->saveSavedQuery($target, $query, $id);
    CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Your query has been saved'));
    return $this->redirectToRoute('console', array('id' => $r['_id']));
  }

  /**
   * @Route("/console/load", name="console-load")
   */
  public function loadQueryAction(Request $request) {
    $params = array(
      'title' => $this->get('translator')->trans('Console'),
      'main_menu_item' => 'console',
    );
    $list = IndexManager::getInstance()->getSavedQueries();
    $params['list'] = $list;
    return $this->render('ctsearch/console.html.twig', $params);
  }

  /**
   * @Route("/console/delete", name="console-delete")
   */
  public function deleteQueryAction(Request $request) {
    $id = $request->get('id');
    $r = IndexManager::getInstance()->deleteSavedQuery($id);
    CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Your query has been deleted'));
    return $this->redirectToRoute('console-load');
  }
  
  private function getFormattedEngineReponse($res){
    $r = array();
    if(isset($res['hits']['total'])){
      $r['total'] = $res['hits']['total'];
    }
    else{
      $r['total'] = 0;
    }
    $r['cols'] = array();
    if(isset($res['hits']['hits'])){
      foreach($res['hits']['hits'] as $index => $hit){
        if(isset($hit['_source'])){
          foreach(array_keys($hit['_source']) as $k){
            if(!in_array($k, $r['cols'])){
              $r['cols'][] = $k;
            }
            if(is_array($hit['_source'][$k])){
              $res['hits']['hits'][$index]['_source'][$k] = $this->dumpVar($res['hits']['hits'][$index]['_source'][$k]);
            }
          }
        }
      }
      $r['hits'] = $res['hits']['hits'];
    }
    else{
      $r['hits'] = array();
    }
    asort($r['cols']);
    return $r;
  }

  private function dumpVar($var) {
    if (is_object($var)) {
      $var = (array) $var;
    }
    if (is_array($var)) {
      $html = '<ul class="ctsearch-dump">';
      foreach ($var as $k => $v) {
        $html .= '<li>' . $k . ' (' . gettype($v) . ')' . ' => ' . $this->dumpVar($v) . '</li>';
      }
      $html .= '</ul>';
    } else {
      $html = $var;
    }
    return $html;
  }

}
