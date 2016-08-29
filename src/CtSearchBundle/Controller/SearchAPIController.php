<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class SearchAPIController extends Controller {

  /**
   * @Route("/search-api/v2", name="search-api-v2")
   */
  public function searchAPIV2Action(Request $request) {
    if ($request->get('mapping') != null) {
      if (count(explode('.', $request->get('mapping'))) == 2) {

        if($request->get('doc_id') != null){
          $res = IndexManager::getInstance()->getClient()->search(array(
            'index' => explode('.', $request->get('mapping'))[0],
            'type' => explode('.', $request->get('mapping'))[1],
            'body' => array(
              'query' => array(
                'ids' => array(
                  'values' => array($request->get('doc_id'))
                )
              )
            )
          ));
          return new Response(json_encode($res, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json;charset=utf-8'));
        }


        $mapping = IndexManager::getInstance()->getMapping(explode('.', $request->get('mapping'))[0], explode('.', $request->get('mapping'))[1]);
        $definition = json_decode($mapping->getMappingDefinition(), true);
        $analyzed_fields = array();
        $nested_analyzed_fields = array();
        foreach ($definition as $field => $field_detail) {
          if ((!isset($field_detail['index']) || $field_detail['index'] == 'analyzed') && $field_detail['type'] == 'string') {
            $analyzed_fields[] = $field;
          }
          if ($field_detail['type'] == 'nested') {
            foreach ($field_detail['properties'] as $sub_field => $sub_field_detail) {
              if ((!isset($sub_field_detail['index']) || $sub_field_detail['index'] == 'analyzed') && $sub_field_detail['type'] == 'string') {
                $nested_analyzed_fields[] = $field . '.' . $sub_field;
              }
            }
          }
        }
        $query = array();
        if (count($nested_analyzed_fields) > 0) {
          $query['query']['bool']['should'][0]['query_string'] = array(
            'query' => $request->get('query') != null ? $request->get('query') : '*',
            'default_operator' => 'AND',
            'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
            'fields' => $analyzed_fields
          );
          foreach ($nested_analyzed_fields as $field) {
            $query['query']['bool']['should'][]['nested'] = array(
              'path' => explode('.', $field)[0],
              'query' => array(
                'query_string' => array(
                  'query' => $request->get('query') != null ? $request->get('query') : '*',
                  'default_operator' => 'AND',
                  'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
                  'fields' => array($field)
                )
              )
            );
          }
        } else {
          $query['query']['query_string'] = array(
            'query' => $request->get('query') != null ? $request->get('query') : '*',
            'default_operator' => 'AND',
            'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
            'fields' => $analyzed_fields
          );
        }

        if ($request->get('facets') != null) {
          $facets = explode(',', $request->get('facets'));
          foreach ($facets as $facet) {
            if (strpos($facet, '.') === FALSE) {
              $query['aggs'][$facet]['terms'] = array(
                'field' => $facet
              );
            } else {
              $facet_parts = explode('.', $facet);
              if (count($facet_parts) == 3 && $facet_parts[2] == 'raw') {
                $query['aggs'][$facet]['nested']['path'] = $facet_parts[0];
                $query['aggs'][$facet]['aggs'][$facet]['terms'] = array(
                  'field' => $facet
                );
              } elseif (count($facet_parts) == 2 && $facet_parts[1] == 'raw') {
                $query['aggs'][$facet]['terms'] = array(
                  'field' => $facet
                );
              } elseif (count($facet_parts) == 2) {
                $query['aggs'][$facet]['nested']['path'] = $facet_parts[0];
                $query['aggs'][$facet]['aggs'][$facet]['terms'] = array(
                  'field' => $facet
                );
              }
            }
          }
        }
        if ($request->get('facetOptions') != null) {
          foreach ($request->get('facetOptions') as $option) {
            $option_parts = explode(',', $option);
            if (count($option_parts == 3)) {
              switch ($option_parts[1]) {
                case 'size':
                  if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms'])) {
                    $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms']['size'] = $option_parts[2];
                  } elseif (isset($query['aggs'][$option_parts[0]]['terms'])) {
                    $query['aggs'][$option_parts[0]]['terms']['size'] = $option_parts[2];
                  }
                  break;
                case 'order':
                  if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms'])) {
                    $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms']['order'] = array($option_parts[2] => 'asc');
                  } elseif (isset($query['aggs'][$option_parts[0]]['terms'])) {
                    $query['aggs'][$option_parts[0]]['terms']['order'] = array($option_parts[2] => 'asc');
                  }
                  break;
                case 'custom_def':
                  if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]])) {
                    $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]] = json_decode($option_parts[2], true);
                  } elseif (isset($query['aggs'][$option_parts[0]])) {
                    $query['aggs'][$option_parts[0]] = json_decode($option_parts[2], true);
                  }
                  break;
              }
            }
          }
        }

        $applied_facets = array();
        $refactor_for_boolean_query = FALSE;
        if ($request->get('filter') != null) {
          $filters = array();
          foreach ($request->get('filter') as $filter) {
            preg_match('/(?P<name>[^!=><]*)(?P<operator>[!=><]+)"(?P<value>[^"]*)"/', $filter, $matches);
            if (isset($matches['name']) && isset($matches['operator']) && isset($matches['value'])) {
              $filters[] = array(
                'field' => $matches['name'],
                'operator' => $matches['operator'],
                'value' => $matches['value']
              );
              if(!in_array($matches['name'], $applied_facets)){
                $applied_facets[] = $matches['name'];
              }
            }
          }
          if (count($filters) > 0) {
            $refactor_for_boolean_query = TRUE;
            $query['query'] = array(
              'bool' => array(
                'must' => array($query['query'])
              )
            );
            foreach ($filters as $filter) {
              switch ($filter['operator']) {
                case '=':
                  $subquery = array(
                    'term' => array(
                      $filter['field'] => $filter['value']
                    )
                  );
                  break;
                case '!=':
                  $subquery = array(
                    'term' => array(
                      $filter['field'] => $filter['value']
                    )
                  );
                  break;
                case '>=':
                  $subquery = array(
                    'range' => array(
                      $filter['field'] => array(
                        'gte' => $filter['value']
                      )
                    )
                  );
                  break;
                case '>':
                  $subquery = array(
                    'range' => array(
                      $filter['field'] => array(
                        'gt' => $filter['value']
                      )
                    )
                  );
                  break;
                case '<=':
                  $subquery = array(
                    'range' => array(
                      $filter['field'] => array(
                        'lte' => $filter['value']
                      )
                    )
                  );
                  break;
                case '<':
                  $subquery = array(
                    'range' => array(
                      $filter['field'] => array(
                        'lt' => $filter['value']
                      )
                    )
                  );
                  break;
                default:
                  if (isset($subquery)) {
                    unset($subquery);
                  }
                  break;
              }
              if (isset($subquery)) {
                $field_parts = explode('.', $filter['field']);
                if (count($field_parts) == 2 && $field_parts[1] != 'raw' || count($field_parts) == 3) {
                  $query['query']['bool'][$filter['operator'] != '!=' ? 'must' : 'must_not'][] = array(
                    'nested' => array(
                      'path' => $field_parts[0],
                      'query' => $subquery
                    )
                  );
                } else {
                  $query['query']['bool'][$filter['operator'] != '!=' ? 'must' : 'must_not'][] = $subquery;
                }
              }
            }
          }
        }
        if ($request->get('ids') != null) {
          $ids = array_map('trim', explode(',', $request->get('ids')));
          if(!$refactor_for_boolean_query) {
            $refactor_for_boolean_query = TRUE;
            $query['query'] = array(
              'bool' => array(
                'must' => array($query['query'])
              )
            );
          }
          $query['query']['bool']['must'][] = array(
            'ids' => array(
              'values' => $ids
            )
          );
        }
        if ($request->get('qs_filter') != null) {
          $qs_filters = array();
          foreach ($request->get('qs_filter') as $qs_filter) {
            preg_match('/(?P<name>[^=]*)="(?P<value>[^"]*)"/', $qs_filter, $matches);
            if (isset($matches['name']) && isset($matches['value'])) {
              $qs_filters[] = array(
                'field' => $matches['name'],
                'value' => $matches['value']
              );
            }
          }
          if (count($qs_filters) > 0 && !$refactor_for_boolean_query  ) {
            $query['query'] = array(
              'bool' => array(
                'must' => array($query['query'])
              )
            );
          }
          foreach($qs_filters as $qs_filter){
            if(count(explode(".", $qs_filter['field'])) > 1){
              $query['query']['bool']['must'][]['nested'] = array(
                'path' => explode('.', $qs_filter['field'])[0],
                'query' => array(
                  'query_string' => array(
                    'query' => $qs_filter['field'] . ':"' . $qs_filter['value'] . '"',
                    'default_operator' => 'AND',
                    'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
                    'fields' => array($qs_filter['field'])
                  )
                )
              );
            }
            else{
              $query['query']['bool']['must'][]['query_string'] = array(
                'query' => $qs_filter['field'] . ':"' . $qs_filter['value'] . '"',
                'default_operator' => 'AND',
                'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
                'fields' => array($qs_filter['field'])
              );
            }
          }
        }
        
        if($request->get('sort') != null && count(explode(',', $request->get('sort'))) == 2){
          $query['sort'] = array(
            explode(',', $request->get('sort'))[0] => array(
              'order' => strtolower(explode(',', $request->get('sort'))[1])
            )
          );
        }

        if($request->get('highlights') != null){
          $highlights = array_map('trim', explode(',', $request->get('highlights')));
          foreach($highlights as $highlight){
            $highlight_r = array_map('trim', explode('|', $highlight));
            if(count($highlight_r) == 4){
              $query['highlight']['fields'][$highlight_r[0]] = array(
                'fragment_size' => $highlight_r[1],
                'number_of_fragments' => $highlight_r[2],
                'no_match_size' => $highlight_r[3],
              );
            }
          }
        }

        if($request->get('suggest') != null){
          $suggest_fields = array_map('trim', explode(',', $request->get('suggest')));
          foreach($suggest_fields as $i => $field){
            $query['suggest'][$field] = array(
              'text' => $request->get('query'),
              'term' => array(
                'field' => $field
              )
            );
          }
        }

        try {
          $res = IndexManager::getInstance()->search(explode('.', $request->get('mapping'))[0], json_encode($query), $request->get('from') != null ? $request->get('from') : 0,  $request->get('size') != null ? $request->get('size') : 10);
          IndexManager::getInstance()->saveStat($request->get('mapping'), $applied_facets, $request->get('query') != null ? $request->get('query') : '', $request->get('analyzer'), $request->getQueryString(), isset($res['hits']['total']) ? $res['hits']['total'] : 0, isset($res['took']) ? $res['took'] : 0, $request->get('clientIp') != null ? $request->get('clientIp') : $request->getClientIp(), $request->get('tag') != null ? $request->get('tag') : '');
          if(isset($res['suggest'])){
            $suggestions = array();
            foreach($res['suggest'] as $field => $suggests){
              foreach($suggests as $suggest) {
                if (isset($suggest['options'])) {
                  foreach ($suggest['options'] as $suggestion) {
                    $suggestions[] = array(
                      'field' => $field,
                      'text' => $suggestion['text'],
                      'score' => $suggestion['score'],
                      'freq' => $suggestion['freq'],
                    );
                  }
                }
              }
            }
            usort($suggestions, function($a, $b){
              if($a['freq'] == $b['freq'])
                return $a['score'] < $b['score'];
              return $a['freq'] < $b['freq'];
            });
            $res['suggest_ctsearch'] = $suggestions;
          }
        } catch (\Exception $ex) {
          return new Response(json_encode(array('error' => $ex->getMessage())), 500, array('Content-type' => 'application/json;charset=utf-8'));
        }
        if(isset($res['hits'])){
          if(isset($res['aggregations'])){
            foreach($res['aggregations'] as $agg_name => $agg){
              if(isset($agg[$agg_name])){
                $res['aggregations'][$agg_name] = $agg[$agg_name];
              }
            }
          }
          return new Response(json_encode($res, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json;charset=utf-8'));
        }
        else{
          return new Response('{"error": "Search failed"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
        }
      } else {
        return new Response('{"error": "Mapping does not exists"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
    } else {
      return new Response('{"error": "Missing mapping parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    }
  }

}
