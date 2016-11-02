<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class SearchPageController extends Controller {

  /**
   * @Route("/search-pages", name="search-pages")
   */
  public function listSearchPagesAction(Request $request) {
    $searchPages = IndexManager::getInstance()->getSearchPages($this);
    return $this->render('ctsearch/search-page.html.twig', array(
        'title' => $this->get('translator')->trans('Search pages'),
        'main_menu_item' => 'search-pages',
        'searchPages' => $searchPages
    ));
  }

  /**
   * @Route("/search-pages/add", name="search-page-add")
   */
  public function addSearchPageAction(Request $request) {
    return $this->handleAddOrEditSearchPage($request);
  }

  /**
   * @Route("/search-pages/edit", name="search-page-edit")
   */
  public function editSearchPageAction(Request $request) {
    return $this->handleAddOrEditSearchPage($request, $request->get('id'));
  }

  /**
   * @Route("/search-pages/delete", name="search-page-delete")
   */
  public function deleteSearchPageAction(Request $request) {
    if ($request->get('id') != null) {
      IndexManager::getInstance()->deleteSearchPage($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Search page has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('search-pages'));
  }

  /**
   * @Route("/search-pages/fields/{mapping}", name="search-page-list-fields")
   */
  public function getMappingFields(Request $request, $mapping){

    $r = array();
    if(count(explode('.', $mapping)) > 1){
      $indexName = explode('.', $mapping)[0];
      $type = explode('.', $mapping)[1];
      $type = IndexManager::getInstance()->getMapping($indexName, $type);
      if($type != null){
        $definition = json_decode($type->getMappingDefinition(), TRUE);
        foreach($definition as $field_name => $field){
          $r = array_merge($r, $this->getFieldDefitions($field, $field_name));
        }
      }
    }

    return new Response(json_encode($r, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json; charset=utf-8'));
  }

  private function getFieldDefitions($field, $parent)
  {
    $r = array();
    if(isset($field['type']) && isset($field['properties']) && $field['type'] == 'nested'){
      foreach($field['properties'] as $field_name => $child){
        $r = array_merge($r, $this->getFieldDefitions($child, $parent . '.' . $field_name));
      }
    }
    else{
      $r[] = $parent;
      if(isset($field['fields'])){
        foreach($field['fields'] as $field_name => $child){
          $r = array_merge($r, $this->getFieldDefitions($child, $parent . '.' . $field_name));
        }
      }
    }
    return $r;
  }

  private function handleAddOrEditSearchPage($request, $id = null) {
    if ($id == null) { //Add
      $searchPage = new \CtSearchBundle\Classes\SearchPage('', '', '{}');
    } else { //Edit
      $searchPage = IndexManager::getInstance()->getSearchPage($request->get('id'));
      $searchPage->setDefinition(json_encode($searchPage->getDefinition(), JSON_PRETTY_PRINT));
    }
    $info = IndexManager::getInstance()->getElasticInfo();
    $mappingChoices = array();
    foreach ($info as $k => $data) {
      foreach($data['mappings'] as $mapping){
        $mappingChoices[$k . '.' . $mapping['name']] = $k . '.' . $mapping['name'];
      }
    }
    asort($mappingChoices);
    $mappingChoices = array_merge(array(
      $this->get('translator')->trans('Select a mapping') => ''
    ), $mappingChoices);
    $form = $this->createFormBuilder($searchPage)
      ->add('id', HiddenType::class)
      ->add('name', TextType::class, array(
        'label' => $this->get('translator')->trans('Search page name'),
        'required' => true,
      ))
      ->add('mapping', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Mapping'),
        'choices' => $mappingChoices,
        'required' => true
      ))
      ->add('definition', TextareaType::class, array(
        'label' => $this->get('translator')->trans('JSON Definition'),
        'required' => true
      ))
      ->add('save', SubmitType::class, array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      if (json_decode($searchPage->getDefinition()) == null) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('JSON parsing failed.'));
      } else {
        IndexManager::getInstance()->saveSearchPage($form->getData());
        if ($id == null) {
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('New search page has been added successfully'));
        } else {
          CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Search page has been updated successfully'));
        }
        if ($id == null)
          return $this->redirect($this->generateUrl('search-pages'));
        else {
          return $this->redirect($this->generateUrl('search-page-edit', array('id' => $id)));
        }
      }
    }
    return $this->render('ctsearch/search-page.html.twig', array(
        'title' => $id == null ? $this->get('translator')->trans('New search page') : $this->get('translator')->trans('Edit search page'),
        'main_menu_item' => 'search-pages',
        'form' => $form->createView()
    ));
  }

  /**
   * @Route("/logs", name="show-logs")
   */
  public function showLogsAction(Request $request) {
    $datasourceChoices = array(
      $this->get('translator')->trans('Select a datasource') => '',
    );
    $datasources = IndexManager::getInstance()->getDatasources(null);
    foreach($datasources as $datasource){
      $datasourceChoices[$datasource->getName()] = $datasource->getName();
    }
    $filters = array(
      'date_from' => \DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d') . ' 00:00'),
      'date_to' => \DateTime::createFromFormat('Y-m-d H:i', date('Y-m-d') . ' 23:59'),
      'from' => $request->get('from') != null ? $request->get('from') : 0,
    );
    $form = $this->createFormBuilder($filters)
      ->setMethod('GET')
      ->add('from', HiddenType::class)
      ->add('log_type', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Log type'),
        'choices' => array(
          $this->get('translator')->trans('Select a log type') => '',
          $this->get('translator')->trans('Error') => 'error',
          $this->get('translator')->trans('Debug') => 'debug',
        ),
        'required' => false
      ))
      ->add('datasource_name', ChoiceType::class, array(
        'label' => $this->get('translator')->trans('Datasource'),
        'choices' => $datasourceChoices,
        'required' => false
      ))
      ->add('date_from', DateTimeType::class, array(
        'label' => $this->get('translator')->trans('Date from'),
        'date_widget' => "single_text",
        'time_widget' => "single_text",
        'required' => false
      ))
      ->add('date_to', DateTimeType::class, array(
        'label' => $this->get('translator')->trans('Date to'),
        'date_widget' => "single_text",
        'time_widget' => "single_text",
        'required' => false
      ))
      ->add('text', TextType::class, array(
        'label' => $this->get('translator')->trans('Text'),
        'required' => false
      ))
      ->add('filter', SubmitType::class, array('label' => $this->get('translator')->trans('Show logs')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      $filters = $form->getData();
      $body = array(
        'query' => array(
          'bool' => array(
            'must' => array(
              array(
                'type' => array(
                  'value' => 'logs'
                ),
              )
            )
          )
        ),
        'sort' => array(
          'date' => 'desc'
        )
      );

      if(!empty($filters['log_type'])){
        $body['query']['bool']['must'][] = array(
          'term' => array(
            'type' => $filters['log_type']
          )
        );
      }

      if(!empty($filters['datasource_name'])){
        $body['query']['bool']['must'][] = array(
          'term' => array(
            'log_datasource_name' => $filters['datasource_name']
          )
        );
      }

      if(!empty($filters['date_from'])){
        $body['query']['bool']['must'][] = array(
          'range' => array(
            'date' => array(
              'gte' => $filters['date_from']->format('Y-m-d\TH:i:s')
            )
          )
        );
      }

      if(!empty($filters['date_to'])){
        $body['query']['bool']['must'][] = array(
          'range' => array(
            'date' => array(
              'lte' => $filters['date_to']->format('Y-m-d\TH:i:s')
            )
          )
        );
      }

      if(!empty($filters['text'])){
        $body['query']['bool']['must'][] = array(
          'query_string' => array(
            'query' => $filters['text'],
            'analyzer' => 'standard',
            'fields' => array('log_datasource_name', 'message', 'object')
          )
        );
      }

      $res = IndexManager::getInstance()->search('.ctsearch', json_encode($body), (int)$filters['from'], 100);
    }

    return $this->render('ctsearch/log-search.html.twig', array(
      'title' => $this->get('translator')->trans('Logs'),
      'main_menu_item' => 'logs',
      'form' => $form->createView(),
      'logs' => isset($res['hits']['hits']) ? $res['hits']['hits'] : array(),
      'from' => $filters['from']
    ));
  }

  /**
   * @Route("/search-pages/search/{id}", name="search-page-display")
   */
  public function displaySearchPageAction(Request $request, $id)
  {
    $searchPage = IndexManager::getInstance()->getSearchPage($id);

    $params = array(
      'mapping' => $searchPage->getMapping(),
      'sp_id' => $id,
    );

    $url = $this->generateUrl('ct_search_client_homepage', $params);

    return $this->redirect($url);
  }

  /**
   * @Route("/search-pages/more-like-this/{searchPageId}/{docId}/{type}", name="more-like-this-display")
   */
  public function getMoreLikeThisAction(Request $request, $searchPageId = null, $docId = null, $type = null) {

    $searchPage = IndexManager::getInstance()->getSearchPage($searchPageId);

    $customDef = array(
      'query' => array(
        'more_like_this' => array(
          'fields' => $searchPage->getConfig()['more_like_this_fields'],
          'docs' => array(
            array(
              '_index' => $searchPage->getIndexName(),
              '_type' => $type,
              '_id' => $docId,
            )
          ),
          'min_term_freq' => 1,
          'max_query_terms' => 12
        )
      )
    );

    return $this->displaySearchPageAction($request, $searchPageId, null, 4, $customDef, array("_score" => "desc"));
  }

  private function formatDate($format, $str) {
    return date($format, strtotime($str));
  }

  private function truncateArray($array, $max, $excludedKeys = array()) {
    foreach ($array as $k => $v) {
      if (!is_array($v)) {
        if (is_string($v)) {
          if (strlen($v) > $max && !in_array($k, $excludedKeys)) {
            $array[$k] = substr($v, 0, strpos($v, ' ', $max) > 0 ? strpos($v, ' ', $max) : $max) . ' ...';
          }
        }
      } else {
        $array[$k] = $this->truncateArray($v, $max, $excludedKeys);
      }
    }
    return $array;
  }

}
