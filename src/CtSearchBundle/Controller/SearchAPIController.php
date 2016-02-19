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
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      if (count(explode('.', $request->get('mapping'))) == 2) {
        $mapping = $indexManager->getMapping(explode('.', $request->get('mapping'))[0], explode('.', $request->get('mapping'))[1]);
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
            }
          }
          if (count($filters) > 0) {
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
        
        if($request->get('sort') != null && count(explode(',', $request->get('sort'))) == 2){
          $query['sort'] = array(
            explode(',', $request->get('sort'))[0] => array(
              'order' => strtolower(explode(',', $request->get('sort'))[1])
            )
          );
        }
        try {
          $res = $indexManager->search(explode('.', $request->get('mapping'))[0], json_encode($query), $request->get('from') != null ? $request->get('from') : 0,  $request->get('size') != null ? $request->get('size') : 10);
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
