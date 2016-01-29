<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $searchPages = $indexManager->getSearchPages($this);
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
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $indexManager->deleteSearchPage($request->get('id'));
      CtSearchBundle::addSessionMessage($this, 'status', $this->get('translator')->trans('Search page has been deleted'));
    } else {
      CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('No id provided'));
    }
    return $this->redirect($this->generateUrl('search-pages'));
  }

  private function handleAddOrEditSearchPage($request, $id = null) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    if ($id == null) { //Add
      $searchPage = new \CtSearchBundle\Classes\SearchPage('', '', '{}', json_encode(json_decode('{"fields": {"title":"", "url":"", "excerp_fields":[]}, "aggregations":[], "more_like_this_fields": []}'), JSON_PRETTY_PRINT));
    } else { //Edit
      $searchPage = $indexManager->getSearchPage($request->get('id'));
      $searchPage->setDefinition(json_encode($searchPage->getDefinition(), JSON_PRETTY_PRINT));
      $searchPage->setConfig(json_encode($searchPage->getConfig(), JSON_PRETTY_PRINT));
    }
    $info = $indexManager->getElasticInfo();
    $indexChoices = array(
      '' => $this->get('translator')->trans('Select index'),
    );
    foreach ($info as $k => $data) {
      $indexChoices[$k] = $k;
    }
    $form = $this->createFormBuilder($searchPage)
      ->add('id', 'hidden')
      ->add('name', 'text', array(
        'label' => $this->get('translator')->trans('Search page name'),
        'required' => true,
      ))
      ->add('indexName', 'choice', array(
        'label' => $this->get('translator')->trans('Index name'),
        'choices' => $indexChoices,
        'required' => true
      ))
      ->add('definition', 'textarea', array(
        'label' => $this->get('translator')->trans('JSON Definition'),
        'required' => true
      ))
      ->add('config', 'textarea', array(
        'label' => $this->get('translator')->trans('Configuration (JSON)'),
        'required' => true
      ))
      ->add('save', 'submit', array('label' => $this->get('translator')->trans('Save')))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
      if (json_decode($searchPage->getDefinition()) == null || json_decode($searchPage->getConfig()) == null) {
        CtSearchBundle::addSessionMessage($this, 'error', $this->get('translator')->trans('JSON parsing failed.'));
      } else {
        $indexManager->saveSearchPage($form->getData());
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
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $res = $indexManager->search('.ctsearch', json_encode(array(
      'query' => array(
        'filtered' => array(
          'query' => array(
            'term' => array(
              'name' => 'logs'
            ),
          ),
          'filter' => array(
            'type' => array(
              'value' => 'search_page'
            )
          )
        )
      )
    )));
    //var_dump($res);
    if (!isset($res['hits']['total']) || $res['hits']['total'] == 0) {
      $pageDef = file_get_contents(__DIR__ . '/../Resources/ctsearch_logs_searchpage_definition.json');
      $pageConf = file_get_contents(__DIR__ . '/../Resources/ctsearch_logs_searchpage_config.json');
      $searchPage = new \CtSearchBundle\Classes\SearchPage('logs', '.ctsearch', $pageDef, $pageConf, NULL);
      $r = $indexManager->saveSearchPage($searchPage);
      $searchPage->setId($r['_id']);
    } else {
      $hit = $res['hits']['hits'][0];
      $searchPage = new \CtSearchBundle\Classes\SearchPage($hit['_source']['name'], $hit['_source']['index_name'], unserialize($hit['_source']['definition']), unserialize($hit['_source']['config']), $hit['_id']);
    }
    return $this->displaySearchPageAction($request, $searchPage->getId(), 'ctsearch/log-search.html.twig', 50);
  }

  /**
   * @Route("/search-pages/search/{id}", name="search-page-display")
   */
  public function displaySearchPageAction(Request $request, $id, $customTpl = null, $custom_page_size = 0, $customDef = null, $sort = null) {
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $searchPage = $indexManager->getSearchPage($id);

    $definition = json_encode($searchPage->getDefinition());
    $suggest_query = null;
    if ($request->get("q") != null) {
      $definition = str_replace("@search_query@", addslashes(str_replace("'", " ", $request->get("q"))), $definition);
      if (isset($searchPage->getConfig()['suggester_field'])) {
        $suggest_query = array(
          'suggest' => array(
            'text' => addslashes(str_replace("'", " ", $request->get("q"))),
            'simple_phrase' => array(
              'phrase' => array(
                'field' => $searchPage->getConfig()['suggester_field'],
                'size' => 10,
                'real_word_error_likelihood' => 0.05,
                'max_errors' => 0.9,
                "gram_size" => 2,
                'direct_generator' => array(
                  array(
                    'field' => $searchPage->getConfig()['suggester_field'],
                    'suggest_mode' => 'always',
                    'min_word_length' => 1
                  )
                )
              )
            )
          )
        );
      }
    } else {
      $definition = str_replace("@search_query@", "*", $definition);
    }



    $definition = json_decode($definition, true);
    $filter_queries = array();
    $active_filters = array();
    foreach ($request->query->all() as $k => $v) {
      if (strpos($k, 'agg_') === 0) {
        $agg_name = substr($k, strlen('agg_'));
        if (isset($definition['aggs'][$agg_name]['terms']['field'])) {
          $agg_field = $definition['aggs'][$agg_name]['terms']['field'];
          if (!is_array($v)) {
            $active_filters[$agg_name][] = $v;
            $filter_queries[] = array(
              'term' => array(
                $agg_field => $v,
              ),
            );
          } else {
            foreach ($v as $vv) {
              $active_filters[$agg_name][] = $vv;
              $filter_queries[] = array(
                'term' => array(
                  $agg_field => $vv,
                ),
              );
            }
          }
        }
      }
      if (strpos($k, 'size_agg_') === 0) {
        $agg_name = substr($k, strlen('size_agg_'));
        $definition['aggs'][$agg_name][array_keys($definition['aggs'][$agg_name])[0]]['size'] = $v;
      }
    }
    if (count($filter_queries) > 0) {
      $original_query = $customDef != null && isset($customDef['query']) ? $customDef['query'] : $definition['query'];
      $definition['query'] = array(
        'bool' => array(
          'must' => array($original_query),
        ),
      );
      foreach ($filter_queries as $query) {
        $definition['query']['bool']['must'][] = $query;
      }
    } elseif ($customDef != null && isset($customDef['query'])) {
      $definition['query'] = $customDef['query'];
    }

    $querystring = $request->get("q") != null ? $request->get("q") : '';
    $offset = $request->get("offset") != null ? $request->get("offset") : 0;
    $page_size = $custom_page_size > 0 ? $custom_page_size : 10;
    if ($sort != null) {
      $definition['sort'] = $sort;
    }
    try {
      $result = $indexManager->search($searchPage->getIndexName(), json_encode($definition), $offset, $page_size);
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
        if ($suggest_query != null) {
          $didYouMeanResult = $indexManager->search($searchPage->getIndexName(), json_encode($suggest_query));
          if ($didYouMeanResult['suggest'] && count($didYouMeanResult['suggest']['simple_phrase']) > 0 && count($didYouMeanResult['suggest']['simple_phrase'][0]['options']) > 0) {
            $didYouMean = $didYouMeanResult['suggest']['simple_phrase'][0]['options'][0]['text'];
          }
        }
      } else {
        foreach ($result['hits']['hits'] as &$hit) {
          if ($customTpl == null) {
            $hit['_source'] = $this->truncateArray($hit['_source'], 300, array('image', 'title'));
            $hit['highlight'] = $this->truncateArray($hit['highlight'], 300, array('image', 'title'));
          }
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
          $buckets = array();
          $agg_res = array();
          if (isset($result['aggregations'][$agg['name']]['buckets'])) {
            $buckets = $result['aggregations'][$agg['name']]['buckets'];
            $agg_res = $result['aggregations'][$agg['name']];
          } elseif (isset($result['aggregations'][$agg['name']][$agg['name']]['buckets'])) {
            $buckets = $result['aggregations'][$agg['name']][$agg['name']]['buckets'];
            $agg_res = $result['aggregations'][$agg['name']][$agg['name']];
          }
          foreach ($buckets as $bucket) {
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
                $bucket_value = $this->formatDate($agg['date_format'], $bucket_value);
              } catch (Exception $ex) {
                
              }
            }
            $params = array_merge(array('id' => $id), $request->query->all());
            if (isset($params['offset']))
              unset($params['offset']);
            if (isset($params['agg_' . $agg['name']])) {
              if (!is_array($params['agg_' . $agg['name']])) {
                $params['agg_' . $agg['name']] = array($params['agg_' . $agg['name']]);
              }
              $params['agg_' . $agg['name']][] = $agg_key;
            } else {
              $params['agg_' . $agg['name']] = $agg_key;
            }
            $agg_entry = array(
              'key' => $bucket_value,
              'doc_count' => $bucket['doc_count'],
              'link' => $this->generateUrl($request->get('_route'), $params)
            );
            if (isset($active_filters[$agg['name']]) && in_array($agg_key, $active_filters[$agg['name']])) {
              $agg_entry['active'] = true;
              $params = array_merge(array('id' => $id), $request->query->all());
              if (isset($params['offset']))
                unset($params['offset']);
              if (is_array($params['agg_' . $agg['name']])) {
                unset($params['agg_' . $agg['name']][$agg_key]);
              } else {
                unset($params['agg_' . $agg['name']]);
              }
              $agg_entry['remove_url'] = $this->generateUrl($request->get('_route'), $params);
            }
            $result['_ctsearch']['aggregations'][$agg['name']]['values'][] = $agg_entry;
          }
          if ($agg_res['sum_other_doc_count'] > 0) {
            $params = array_merge(array('id' => $id), $request->query->all());
            if (isset($params['offset']))
              unset($params['offset']);
            $params['size_agg_' . $agg['name']] = count($agg_res['buckets']) + (isset($definition['aggs'][$agg['name']][array_keys($definition['aggs'][$agg['name']])[0]]['size']) ? $definition['aggs'][$agg['name']][array_keys($definition['aggs'][$agg['name']])[0]]['size'] : 10);
            $result['_ctsearch']['aggregations'][$agg['name']]['see_more_link'] = $this->generateUrl($request->get('_route'), $params);
          }
          if ($agg_value_count == 0)
            unset($result['_ctsearch']['aggregations'][$agg['name']]);
        }
      }
      if ($global_value_count == 0)
        unset($result['_ctsearch']['aggregations']);
    }
    //var_dump($definition);
    //var_dump($result);

    $pager = '';
    if (isset($result['hits']['total'])) {
      $qs_params = array_merge(array('id' => $id), $request->query->all());
      if (isset($qs_params['offset']))
        unset($qs_params['offset']);
      if (!isset($qs_params['q']))
        $qs_params['q'] = '';
      $qs = $this->generateUrl($request->get('_route'), $qs_params);
      $nb_pages = ceil($result['hits']['total'] / $page_size);
      $current_page = $offset / $page_size + 1;
      if ($nb_pages > 1) {
        for ($i = $current_page; $i >= ($current_page - 3) && $i >= 1; $i--) {
          $pager = '<li' . ($i == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=' . (($i - 1) * $page_size) . '" class="paging-link">' . $i . '</a></li>' . $pager;
        }
        if ($i > 1)
          $pager = '<li' . (1 == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=0" class="paging-link">1</a></li><li>...</li>' . $pager;
        if ($i == 1)
          $pager = '<li' . (1 == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=0" class="paging-link">1</a></li>' . $pager;
        for ($i = $current_page + 1; $i <= ($current_page + 3) && $i <= $nb_pages; $i++) {
          $pager .= '<li' . ($i == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=' . (($i - 1) * $page_size) . '" class="paging-link">' . $i . '</a></li>';
        }
        if ($i < $nb_pages) {
          $pager .= '<li>...</li><li' . ($nb_pages == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=' . (($nb_pages - 1) * $page_size) . '" class="paging-link">' . $nb_pages . '</a></li>';
        }
        if ($i == $nb_pages) {
          $pager .= '<li' . ($nb_pages == $current_page ? ' class="active"' : '') . '><a href="' . $qs . '&offset=' . (($nb_pages - 1) * $page_size) . '" class="paging-link">' . $nb_pages . '</a></li>';
        }
        if ($current_page != $nb_pages) {
          $pager .= '<li class="next"><a href="' . $qs . '&offset=' . ($current_page * $page_size) . '">&gt;&nbsp;' . $this->get('translator')->trans('Next') . '</a></li>';
        }
        if ($current_page != 1) {
          $pager = '<li class="prev"><a href="' . $qs . '&offset=' . (($current_page - 2) * $page_size) . '">&lt;&nbsp;' . $this->get('translator')->trans('Previous') . '</a></li>' . $pager;
        }
        $pager = '<ul class="pager">' . $pager . '</ul>';
      }
    }


    $vars = array(
      'title' => $this->get('translator')->trans('Search page'),
      'results' => $result,
      'searchPage' => $searchPage,
      'querystring' => $querystring,
      'pager' => $pager,
      'base_url' => $this->generateUrl($request->get('_route'), array('id' => $id))
    );
    if (isset($result['aggregations'])) {
      $vars['facets'] = $result['aggregations'];
    }
    if (isset($didYouMean))
      $vars['didYouMean'] = $didYouMean;
    if ($indexManager->getACSettings($searchPage->getIndexName()) != null)
      $vars['autocomplete'] = true;
    else
      $vars['autocomplete'] = false;
    return $this->render($customTpl == null ? 'ctsearch/base-search.html.twig' : $customTpl, $vars);
  }

  /**
   * @Route("/search-pages/autocomplete/{searchPageId}/{text}", name="get-autocomplete")
   */
  public function getAutocomplete(Request $request, $searchPageId, $text) {

    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $searchPage = $indexManager->getSearchPage($searchPageId);
    $data = array();

    $text_transliterate_tokens = $indexManager->getClient()->indices()->analyze(array(
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
      $res = $indexManager->search($searchPage->getIndexName(), json_encode($ac_query), 0, 10);
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

    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
    $searchPage = $indexManager->getSearchPage($searchPageId);

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
    $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));

    $searchPage = $indexManager->getSearchPage($id);

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
      $result = $indexManager->search($searchPage->getIndexName(), $query, $offset, $page_size);
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
