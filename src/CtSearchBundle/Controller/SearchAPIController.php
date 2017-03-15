<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

define('SEARCH_API_DEBUG', false);

class SearchAPIController extends Controller
{

  /**
   * @Route("/search-api/v2", name="search-api-v2")
   */
  public function searchAPIV2Action(Request $request)
  {
    if ($request->get('mapping') != null) {
      if (count(explode('.', $request->get('mapping'))) == 2) {

        if ($request->get('doc_id') != null) {
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
        $stickyFacets = $request->get('sticky_facets') != NULL ? array_map('trim', explode(',', $request->get('sticky_facets'))) : [];
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

        $query_string = $request->get('query') != null ? $request->get('query') : '*';
        if($request->get('escapeQuery') == null || $request->get('escapeQuery') == 1) {
          $query_string = str_replace(':', '\:', $query_string);
          $query_string = str_replace('!', '\!', $query_string);
          $query_string = str_replace('?', '\?', $query_string);
        }

        if (count($nested_analyzed_fields) > 0) {
          $query['query']['bool']['should'][0]['query_string'] = array(
            'query' => $query_string,
            'default_operator' => 'AND',
            'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
            'fields' => $analyzed_fields
          );
          foreach ($nested_analyzed_fields as $field) {
            $query['query']['bool']['should'][]['nested'] = array(
              'path' => explode('.', $field)[0],
              'query' => array(
                'query_string' => array(
                  'query' => $query_string,
                  'default_operator' => 'AND',
                  'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
                  'fields' => array($field)
                )
              )
            );
          }
        } else {
          $query['query']['query_string'] = array(
            'query' => $query_string,
            'default_operator' => 'AND',
            'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
            'fields' => $analyzed_fields
          );
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
              if (!in_array($matches['name'], $applied_facets)) {
                $applied_facets[] = $matches['name'];
              }
            }
          }
          if(SEARCH_API_DEBUG)
            var_dump($filters);
          if (count($filters) > 0) {
            $refactor_for_boolean_query = TRUE;
            $query['query'] = array(
              'bool' => array(
                'must' => array($query['query'])
              )
            );
            $filterQueries = array();
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
                    'bool' => array(
                      'must_not' => array(
                        'term' => array(
                          $filter['field'] => $filter['value']
                        )
                      )
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
                case '<=>':
                  $subquery = array(
                    'range' => array(
                      $filter['field'] => array(
                        'gte' => explode(',', $filter['value'])[0],
                        'lt' => explode(',', $filter['value'])[1]
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
                $filterQueries[$filter['field']][] = $subquery;
              }
            }
            if(SEARCH_API_DEBUG)
              var_dump($filterQueries);
            $query['query']['bool']['filter'] = $this->computeFilter($filterQueries);
          }
        }
        if ($request->get('ids') != null) {
          $ids = array_map('trim', explode(',', $request->get('ids')));
          if (!$refactor_for_boolean_query) {
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
          if (count($qs_filters) > 0 && !$refactor_for_boolean_query) {
            $query['query'] = array(
              'bool' => array(
                'must' => array($query['query'])
              )
            );
          }
          foreach ($qs_filters as $qs_filter) {
            if (count(explode(".", $qs_filter['field'])) > 1) {
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
            } else {
              $query['query']['bool']['must'][]['query_string'] = array(
                'query' => $qs_filter['field'] . ':"' . $qs_filter['value'] . '"',
                'default_operator' => 'AND',
                'analyzer' => $request->get('analyzer') != null ? $request->get('analyzer') : 'standard',
                'fields' => array($qs_filter['field'])
              );
            }
          }
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

          if ($request->get('facetOptions') != null) {
            foreach ($request->get('facetOptions') as $option) {
              $option_parts = explode(',', $option);
              $option_def = substr($option, strpos($option, ',', strpos($option, ',') + 1) + 1);
              if (count($option_parts == 3)) {
                switch ($option_parts[1]) {
                  case 'size':
                    if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms'])) {
                      $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms']['size'] = $option_def;
                    } elseif (isset($query['aggs'][$option_parts[0]]['terms'])) {
                      $query['aggs'][$option_parts[0]]['terms']['size'] = $option_def;
                    }
                    break;
                  case 'order':
                    if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms'])) {
                      $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]]['terms']['order'] = array($option_def => 'asc');
                    } elseif (isset($query['aggs'][$option_parts[0]]['terms'])) {
                      $query['aggs'][$option_parts[0]]['terms']['order'] = array($option_def => 'asc');
                    }
                    break;
                  case 'custom_def':
                    if (isset($query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]])) {
                      $query['aggs'][$option_parts[0]]['aggs'][$option_parts[0]] = json_decode($option_def, true);
                    } elseif (isset($query['aggs'][$option_parts[0]])) {
                      $query['aggs'][$option_parts[0]] = json_decode($option_def, true);
                    }
                    break;
                }
              }
            }
          }

          if(isset($query['query']['bool']['filter'])) {
            foreach ($query['aggs'] as $agg_name => $agg) {
              if(in_array($agg_name, $stickyFacets)) {
                $query['aggs']['sticky_' . $agg_name] = array(
                  'global' => new \stdClass(),
                  'aggs' => array(
                    'sticky_' . $agg_name => array(
                      'filter' => $this->computeFilter($filterQueries, $agg_name),//Put null in $skipField to disable sticky facets
                      'aggs' => array(
                        'sticky_' . $agg_name => $agg
                      )
                    )
                  )
                );
              }
            }
          }
        }


        if ($request->get('sort') != null && count(explode(',', $request->get('sort'))) == 2) {
          $query['sort'] = array(
            explode(',', $request->get('sort'))[0] => array(
              'order' => strtolower(explode(',', $request->get('sort'))[1])
            )
          );
        }

        if ($request->get('highlights') != null) {
          $highlights = array_map('trim', explode(',', $request->get('highlights')));
          foreach ($highlights as $highlight) {
            $highlight_r = array_map('trim', explode('|', $highlight));
            if (count($highlight_r) == 4) {
              $query['highlight']['fields'][$highlight_r[0]] = array(
                'fragment_size' => $highlight_r[1],
                'number_of_fragments' => $highlight_r[2],
                'no_match_size' => $highlight_r[3],
              );
            }
          }
        }

        if ($request->get('suggest') != null) {
          $suggest_fields = array_map('trim', explode(',', $request->get('suggest')));
          foreach ($suggest_fields as $i => $field) {
            $text = substr($query_string, 0, 99);
            $query['suggest'][$field] = array(
              'text' => $text,
              'term' => array(
                'field' => $field
              )
            );
          }
        }


        if(SEARCH_API_DEBUG)
          var_dump($query);
        try {
          $res = IndexManager::getInstance()->search(explode('.', $request->get('mapping'))[0], json_encode($query), $request->get('from') != null ? $request->get('from') : 0, $request->get('size') != null ? $request->get('size') : 10, explode('.', $request->get('mapping'))[1]);
          if($request->get('escapeQuery') == null || $request->get('escapeQuery') == 1) {
            IndexManager::getInstance()->saveStat($request->get('mapping'), $applied_facets, $request->get('query') != null ? $request->get('query') : '', $request->get('analyzer'), $request->getQueryString(), isset($res['hits']['total']) ? $res['hits']['total'] : 0, isset($res['took']) ? $res['took'] : 0, $request->get('clientIp') != null ? $request->get('clientIp') : $request->getClientIp(), $request->get('tag') != null ? $request->get('tag') : '');
          }
          if (isset($res['suggest'])) {
            $suggestions = array();
            foreach ($res['suggest'] as $field => $suggests) {
              foreach ($suggests as $suggest) {
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
            usort($suggestions, function ($a, $b) {
              if ($a['freq'] == $b['freq'])
                return $a['score'] < $b['score'];
              return $a['freq'] < $b['freq'];
            });
            $res['suggest_ctsearch'] = $suggestions;
          }
          if($query_string != '*' && !empty($query_string) && IndexManager::getInstance()->mappingExists(explode('.', $request->get('mapping'))[0], 'ctsearch_autopromote')){
            $promote_query = json_encode(array(
              'query' => array(
                'query_string' => array(
                  'query' => $query_string,
                  'default_field' => 'ctsap__keywords',
                  'analyzer' => IndexManager::getInstance()->getAutopromoteAnalyzer(explode('.', $request->get('mapping'))[0])
                )
              )
            ));
            $promote = IndexManager::getInstance()->search(explode('.', $request->get('mapping'))[0], $promote_query, 0, 5, 'ctsearch_autopromote');
            if(isset($promote['hits']['hits']) && count($promote['hits']['hits']) > 0){
              $res['autopromote'] = $promote;
            }
          }
        } catch (\Exception $ex) {
          return new Response(json_encode(array('error' => $ex->getMessage())), 500, array('Content-type' => 'application/json;charset=utf-8'));
        }
        if (isset($res['hits'])) {
          if (isset($res['aggregations'])) {
            foreach ($res['aggregations'] as $agg_name => $agg) {
              if (isset($agg[$agg_name])) {
                $res['aggregations'][$agg_name] = $agg[$agg_name];
              }
            }
            foreach ($res['aggregations'] as $agg_name => $agg) {
              if (strpos($agg_name, 'sticky_') === 0) {
                $res['aggregations'][substr($agg_name, strlen('sticky_'))] = $res['aggregations'][$agg_name][$agg_name];
                unset($res['aggregations'][$agg_name]);
              }
            }
          }

          if (isset($res['hits']['hits'])) { //Remove the sort item on hits (pb with json_decode on the client side for too large integer value)
            foreach ($res['hits']['hits'] as $i => $hit) {
              if (isset($hit['sort'])) {
                unset($res['hits']['hits'][$i]['sort']);
              }
            }
          }
          return new Response(json_encode($res, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json;charset=utf-8'));
        } else {
          return new Response('{"error": "Search failed"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
        }
      } else {
        return new Response('{"error": "Mapping does not exists"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
    } else {
      return new Response('{"error": "Missing mapping parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    }
  }

  private function computeFilter($filterQueries, $skipField = NULL){
    $query_filter = array();
    foreach($filterQueries as $field => $queries){
      if($field == $skipField){
        continue;
      }
      if(count($queries) == 1){
        $field_parts = explode('.', $field);
        if (count($field_parts) == 2 && $field_parts[1] != 'raw' || count($field_parts) == 3) {
          $query_filter['bool']['must'][] = array(
            'nested' => array(
              'path' => $field_parts[0],
              'query' => $queries[0]
            )
          );
        } else {
          $query_filter['bool']['must'][] = $queries[0];
        }
      }
      else{
        $compoundQuery = array();
        $field_parts = explode('.', $field);
        foreach($queries as $compoundPart){
          if (count($field_parts) == 2 && $field_parts[1] != 'raw' || count($field_parts) == 3) {
            $compoundQuery['bool']['should'][] = array(
              'nested' => array(
                'path' => $field_parts[0],
                'query' => $compoundPart
              )
            );
          }
          else {
            $compoundQuery['bool']['should'][] = $compoundPart;
          }
        }
        if(SEARCH_API_DEBUG)
          var_dump($compoundQuery);
        $query_filter['bool']['must'][] = $compoundQuery;
      }
    }
    if(empty($query_filter)){
      $query_filter['bool']['must'][] = array(
        'match_all' => array('boost' => 1)
      );
    }
    return $query_filter;
  }

  /**
   * @Route("/search-api/v2/more-like-this", name="search-api-mor-like-this")
   */
  public function seeMoreLikeThisAction(Request $request)
  {
    if ($request->get('mapping') != null && $request->get('doc_id') != null && $request->get('fields') != null) {
      $mapping = $request->get('mapping');
      if (count(explode('.', $mapping)) > 1) {
        $indexName = explode('.', $mapping)[0];
        $type = explode('.', $mapping)[1];
        $body = array(
          'query' => array(
            'more_like_this' => array(
              'fields' => explode(',', $request->get('fields')),
              'like' => array(
                array(
                  '_index' => $indexName,
                  '_type' => $type,
                  '_id' => $request->get('doc_id'),
                )
              )
            )
          )
        );

        $r = IndexManager::getInstance()->search($indexName, json_encode($body), 0, 5);

        if (isset($r['hits']['hits'])) {
          return new Response(json_encode($r['hits']['hits'], JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json;charset=utf-8'));
        } else {
          return new Response('[]', 400, array('Content-type' => 'application/json;charset=utf-8'));
        }
      } else {
        return new Response('{"error": "Mapping format is incorrect"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
    } else {
      return new Response('{"error": "Missing one or more required parameters"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    }
  }


  /**
   * @Route("/search-api/v2/autocomplete", name="search-api-autocomplete")
   */
  public function autocompleteAction(Request $request)
  {
    $mapping = $request->get('mapping');
    $field = $request->get('field');
    $group = $request->get('group');
    $text = $request->get('text');
    $text = $this->transliterate($text);
    $words = explode(' ', $text);
    if(count($words) > 1){
      //$textQuery ;
      foreach($words as $word){
        $textQueries[] = array(
          'wildcard' => array(
            $field => '*' . strtolower($word) . '*'
          )
        );
      }
    }
    else{
      $textQueries[] = array(
        'wildcard' => array(
          $field => '*' . strtolower($text) . '*'
        )
      );
    }
    if ($group != null && !empty($group)) {
      $body = array(
        'query' => array(
          'bool' => array(
            'must' => array(
              $textQueries
            )
          )
        ),
        'size' => 0,
        'aggs' => array(
          $group => array(
            'terms' => array(
              'field' => $group,
              'size' => 999,
              'order' => array(
                '_term' => 'asc'
              )
            ),
            'aggs' => array(
              'tops' => array(
                'top_hits' => array(
                  'size' => 5
                )
              )
            )
          )
        )
      );
    } else {
      $body = array(
        'query' => array(
          'bool' => array(
            'must' => array(
              $textQueries
            )
          )
        ),
        'size' => 10
      );
    }
    if($request->get('filterQuerystring') != null){
      $body['query']['bool']['must'][] = array(
        'query_string' => array(
          'query' => $request->get('filterQuerystring')
        )
      );
    }
    $index = explode('.', $mapping)[0];
    $type = explode('.', $mapping)[1];
    $r = IndexManager::getInstance()->customSearch(array(
      'index' => $index,
      'type' => $type,
      'body' => $body
    ));
    $ret = array();
    if (isset($r['hits']['hits'])) {
      $ret['grouped'] = isset($r['aggregations'][$group]);
      $ret['results'] = array();
      $fieldName = $field;
      if (strpos($fieldName, '.') !== FALSE) {
        $parts = explode('.', $fieldName);
        $fieldName = reset($parts);
      }
      if ($ret['grouped']) {
        foreach ($r['aggregations'][$group]['buckets'] as $group) {
          foreach ($group['tops']['hits']['hits'] as $hit) {
            $ret['results'][$group['key']][] = $hit['_source'][$fieldName];
          }
        }
      } else {
        foreach ($r['hits']['hits'] as $hit) {
          $ret['results'][] = $hit['_source'][$fieldName];
        }
      }
    }
    return new Response(json_encode($ret, JSON_PRETTY_PRINT), 200, array('Content-type' => 'application/json; charset=utf8', 'Access-Control-Allow-Origin' => '*'));
  }

  private function transliterate($str){
    $chars = array("aàáâãäåāąă","AÀÁÂÃÄÅĀĄĂ","cçćč","CÇĆČ","dđď","DĐĎ","eèéêëěēę","EÈÉÊËĚĒĘ","iìíîïī","IÌÍÎÏĪ","lł","LŁ","nñňń","NÑŇŃ","oòóôõöøō","OÒÓÔÕÖØŌ","rř","RŘ","sšśș","SŠŚȘ","tťț","TŤȚ","uùúûüůū","UÙÚÛÜŮŪ","yÿý","YŸÝ","zžżź","ZŽŻŹ");
    $str_c = preg_split('/(?<!^)(?!$)/u', $str );
    $out = '';
    foreach($str_c as $c){
      $repl = false;
      foreach($chars as $char_seq){
        if(strpos($char_seq, $c) !== false){
          $out .= substr($char_seq, 0, 1);
          $repl = true;
          break;
        }
      }
      if(!$repl){
        $out .= $c;
      }
    }
    return $out;
  }

}
