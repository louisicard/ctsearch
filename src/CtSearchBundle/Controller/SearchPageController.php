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
   * @Route("/search-pages/autocomplete/{searchPageId}/{text}", name="get-autocomplete")
   */
  public function getAutocomplete(Request $request, $searchPageId, $text) {

    $searchPage = IndexManager::getInstance()->getSearchPage($searchPageId);
    $data = array();

    $text_transliterate_tokens = IndexManager::getInstance()->getClient()->indices()->analyze(array(
      'index' => $searchPage->getIndexName(),
      'analyzer' => 'ctsearch_ac_transliterate',
      'text' => $text
    ));
    if (isset($text_transliterate_tokens['tokens'])) {
      $text_transliterate = implode(' ', $this->arrayColumn($text_transliterate_tokens['tokens'], 'token'));
      $ac_query = array(
        'query' => array(
          'bool' => array(
            'should' => array(
              array('query_string' => array(
                  'query' => '* ' . $text_transliterate . '*',
                  'default_field' => 'text_transliterate',
                  'analyzer' => 'ctsearch_ac_transliterate'
                )),
              array(
                'query_string' => array(
                  'query' => $text_transliterate . '*',
                  'default_field' => 'text_transliterate',
                  'analyzer' => 'ctsearch_ac_transliterate'
                ))
            )
          )
        ),
        'filter' => array(
          'bool' => array(
            'must' => array(
              'type' => array(
                'value' => '.ctsearch-autocomplete'
              ),
            ),
            'should' => array(
              'range' => array(
                'counter' => array(
                  'gt' => 1
                )
              )
            )
          )
        ),
        'sort' => array(
          '_score' => 'desc',
          'counter' => 'desc'
        )
      );
      $res = IndexManager::getInstance()->search($searchPage->getIndexName(), json_encode($ac_query), 0, 10);
      if (isset($res['hits']['hits'])) {
        foreach ($res['hits']['hits'] as $hit) {
          if ($hit['_score'] > 1)
            $data[] = $hit['_source']['text'];
        }
      }
    }
    return new Response(json_encode(array('took' => isset($res['took']) ? $res['took'] : 0, 'data' => $data)), 200, array('Content-type' => 'application/json;charset=utf-8'));
  }

  private function arrayColumn($array, $column) {
    $r = array();
    foreach ($array as $v) {
      if (isset($v[$column]))
        $r[] = $v[$column];
    }
    return $r;
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

  /**
   * @Route("/search-api/{id}", name="search-api")
   */
  public function searchAPIAction(Request $request, $id) {

    $searchPage = IndexManager::getInstance()->getSearchPage($id);

    $query = json_encode($searchPage->getDefinition());
    $query_string = $request->get('query_string') != null ? $request->get('query_string') : '*';
    $query = str_replace('@search_query@', $query_string, $query);
    $query = json_decode($query, true);

    //var_dump(json_decode($query, true));

    $sort = $request->get("sort");
    $order = $request->get("order") != null ? $request->get("order") : 'asc';
    if ($sort != null) {
      $query['sort'] = array(
        $sort => $order
      );
    }
    $filters = array();
    if ($request->get('filter') != null) {
      foreach ($request->get('filter') as $filter) {
        $logic = "AND";
        $expressions = array();
        if (count(explode('OR', $filter)) > 1) {
          $logic = "OR";
          $expressions = array_map('trim', explode('OR', $filter));
        } elseif (count(explode('AND', $filter)) > 1) {
          $logic = "AND";
          $expressions = array_map('trim', explode('AND', $filter));
        } else {
          $expressions = array(trim($filter));
        }
        $filter_def = array(
          'logic' => $logic,
          'conditions' => array()
        );
        foreach ($expressions as $expr) {
          $cond = array();
          if (preg_match('/(?<field>\w*)\s*(?<operator>\={0,1}\<{0,1}\>{0,1}\={0,1})\s*\"(?<value>.*)\"/', $expr, $cond)) {
            $filter_def['conditions'][] = array(
              'field' => $cond['field'],
              'operator' => $cond['operator'],
              'value' => $cond['value'],
            );
          }
        }
        $filters[] = $filter_def;
      }
    }
    if (count($filters) > 0) {
      if (count($query['query']) > 0) {
        $queries = array($query['query']);
      } else {
        $queries = array();
      }
      foreach ($filters as $filter) {
        if (count($filter['conditions']) > 1) {
          $bool_op = $filter['logic'] == 'AND' ? 'must' : 'should';
          $query_array = array(
            'bool' => array(
              $bool_op => array()
            )
          );
          foreach ($filter['conditions'] as $cond) {
            $query_array['bool'][$bool_op][] = $this->compileQuery($cond['field'], $cond['operator'], $cond['value']);
          }
          $queries[] = $query_array;
        } elseif (count($filter['conditions']) > 0) {
          $queries[] = $this->compileQuery($filter['conditions'][0]['field'], $filter['conditions'][0]['operator'], $filter['conditions'][0]['value']);
        }
      }
      $query['query'] = array(
        'bool' => array(
          'must' => array()
        )
      );
      foreach ($queries as $sub_query) {
        $query['query']['bool']['must'][] = $sub_query;
      }
    }
    $offset = $request->get("offset") != null ? $request->get("offset") : 0;
    $page_size = $request->get("size") != null ? $request->get("size") : 10;
    $query = json_encode($query);
    try {
      $result = IndexManager::getInstance()->search($searchPage->getIndexName(), $query, $offset, $page_size);
      $result['query'] = json_decode($query, true);
    } catch (\Exception $ex) {
      $result = array(
        'hits' => array(
          'total' => 0,
          'hits' => array()
        )
      );
    }
    $config = $searchPage->getConfig();

    if (isset($result['hits']['hits'])) {
      if ($result['hits']['total'] == 0) {
        
      } else {
        foreach ($result['hits']['hits'] as &$hit) {
          $hit['_ctsearch'] = array();
          if (isset($config['fields']['title']) && isset($hit['_source'][$config['fields']['title']])) {
            $hit['_ctsearch']['title'] = $hit['_source'][$config['fields']['title']];
            if (isset($hit['highlight'][$config['fields']['title']])) {
              $hit['_ctsearch']['title'] = implode('<span class="separator"></span>', $hit['highlight'][$config['fields']['title']]);
            } else {
              if (is_array($hit['_source'][$config['fields']['title']]))
                $hit['_ctsearch']['title'] = implode('<span class="separator"></span>', $hit['_source'][$config['fields']['title']]);
              else {
                $hit['_ctsearch']['title'] = $hit['_source'][$config['fields']['title']];
              }
            }
          } else {
            $hit['_ctsearch']['title'] = $this->get('translator')->trans('No title provided in conf for doc #@id@', array('@id@' => $hit['_id']));
          }
          if (isset($config['fields']['url']) && isset($hit['_source'][$config['fields']['url']])) {
            $hit['_ctsearch']['url'] = $hit['_source'][$config['fields']['url']];
          } else {
            $hit['_ctsearch']['url'] = $this->get('translator')->trans('No url');
          }
          if (isset($config['fields']['image']) && isset($hit['_source'][$config['fields']['image']])) {
            $hit['_ctsearch']['image'] = $hit['_source'][$config['fields']['image']];
          }
          if (isset($config['fields']['excerp_fields']) && count($config['fields']['excerp_fields']) > 0) {
            $excerp_html = '';
            foreach ($config['fields']['excerp_fields'] as $excerp) {
              if (isset($excerp['field']) && isset($hit['_source'][$excerp['field']])) {
                if (isset($excerp['date_format'])) {
                  try {
                    if (is_array($hit['_source'][$excerp['field']])) {
                      $execerp_value = array();
                      foreach ($hit['_source'][$excerp['field']] as $execerp_value) {
                        $execerp_value[] = $this->formatDate($excerp['date_format'], $hit['_source'][$excerp['field']]);
                      }
                    } else {
                      $execerp_value = $this->formatDate($excerp['date_format'], $hit['_source'][$excerp['field']]);
                    }
                  } catch (Exception $ex) {
                    $execerp_value = $hit['_source'][$excerp['field']];
                  }
                } else {
                  $execerp_value = $hit['_source'][$excerp['field']];
                }
                $excerp_part = '<div class="excerp-part excerp-part-' . $excerp['field'] . '">';
                if (isset($excerp['label'])) {
                  $excerp_part .= '<div class="excerp-label excerp-label-' . $excerp['field'] . '">' . $excerp['label'] . '</div>';
                }
                $excerp_part .= '<div class="excerp-content excerp-content-' . $excerp['field'] . '">';
                if (isset($hit['highlight'][$excerp['field']])) {
                  $excerp_part .= implode('<span class="separator"></span>', $hit['highlight'][$excerp['field']]);
                } else {
                  if (is_array($execerp_value))
                    $excerp_part .= implode('<span class="separator"></span>', $execerp_value);
                  else
                    $excerp_part .= $execerp_value;
                }
                $excerp_part .= '</div>';
                $excerp_part .= '</div>';
                $excerp_html .= $excerp_part;
              }
            }
            $hit['_ctsearch']['excerp'] = $excerp_html;
          }
          else {
            $hit['_ctsearch']['excerp'] = $this->get('translator')->trans('No excerp fields provided in conf for doc #@id@', array('@id@' => $hit['_id']));
          }
        }
      }
    }
    if (isset($result['aggregations']) && count($result['aggregations']) > 0 && isset($config['aggregations']) && count($config['aggregations']) > 0) {
      $global_value_count = 0;
      $result['_ctsearch']['aggregations'] = array();
      foreach ($config['aggregations'] as $agg) {
        $agg_value_count = 0;
        if (isset($agg['name']) && isset($result['aggregations'][$agg['name']])) {
          $result['_ctsearch']['aggregations'][$agg['name']] = array(
            'label' => isset($agg['label']) ? $agg['label'] : $agg['name'],
            'values' => array(),
          );
          if (isset($result['aggregations'][$agg['name']]['buckets'])) {
            foreach ($result['aggregations'][$agg['name']]['buckets'] as $bucket) {
              $global_value_count++;
              $agg_value_count++;
              $bucket_value = $bucket['key'];
              $agg_key = $bucket['key'];
              if (isset($bucket['key_as_string'])) {
                $bucket_value = $bucket['key_as_string'];
                $agg_key = $bucket['key_as_string'];
              }
              if (isset($agg['date_format'])) {
                try {
                  $display_value = $this->formatDate($agg['date_format'], $bucket_value);
                } catch (Exception $ex) {
                  
                }
              } else {
                $display_value = $bucket_value;
              }
              $agg_entry = array(
                'key' => $bucket_value,
                'display_value' => $display_value,
                'doc_count' => $bucket['doc_count']
              );

              $result['_ctsearch']['aggregations'][$agg['name']]['values'][] = $agg_entry;
            }
          }
          if ($agg_value_count == 0)
            unset($result['_ctsearch']['aggregations'][$agg['name']]);
        }
      }
      if ($global_value_count == 0)
        unset($result['_ctsearch']['aggregations']);
    }

    $headers = array(
      'Content-type' => 'application/json; charset=utf-8'
    );

    return new Response(json_encode($result, JSON_PRETTY_PRINT), 200, $headers);
  }

  private function compileQuery($field, $operator, $value) {
    switch ($operator) {
      case '=':
        return array(
          'term' => array(
            $field => $value
          )
        );
      case '>':
        return array(
          'range' => array(
            $field => array(
              'gt' => $value
            )
          )
        );
      case '<':
        return array(
          'range' => array(
            $field => array(
              'lt' => $value
            )
          )
        );
      case '>=':
        return array(
          'range' => array(
            $field => array(
              'gte' => $value
            )
          )
        );
      case '<=':
        return array(
          'range' => array(
            $field => array(
              'lte' => $value
            )
          )
        );
    }
  }

}
