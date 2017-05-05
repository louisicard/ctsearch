<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 22/03/2016
 * Time: 16:49
 */

namespace CtSearchBundle\Classes;


class KeywordsStatCompiler extends StatCompiler
{
  function getDisplayName()
  {
    return "Keywords statistics";
  }

  function compile($mapping, $from, $to, $period)
  {
    $query = '
      {
      "query": {
          "bool": {
              "must": [{
                "term": {
                  "stat_mapping": "' . $mapping . '"
                }
              }]
          }
      },
      "aggs": {
          "keywords": {
              "nested":{
                "path": "stat_query"
              },
              "aggs":{
                "raw": {
                  "terms": {
                      "field": "stat_query.raw"
                  }
                },
                "analyzed": {
                  "terms": {
                      "field": "stat_query.analyzed"
                  }
                }
              }
          }
      }
    }';
    $query = json_decode($query, TRUE);
    if($from != null) {
      $query['query']['bool']['must'][] = json_decode('{
                    "range": {
                        "stat_date": {
                            "gte": "' . $from->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }
    if($to != null) {
      $query['query']['bool']['must'][] = json_decode('{
                    "range": {
                        "stat_date": {
                            "lte": "' . $to->format('Y-m-d\TH:i') . '"
                        }
                    }
                }', TRUE);
    }
    $query = json_encode($query, JSON_PRETTY_PRINT);

    $res = IndexManager::getInstance()->search(IndexManager::APP_INDEX_NAME, $query, 0, 9999, 'stat');

    if(isset($res['aggregations']['keywords']['analyzed']['buckets'])){
      $data = array();
      foreach($res['aggregations']['keywords']['analyzed']['buckets'] as $bucket){
        $data[] = array(
          $bucket['key'],
          $bucket['doc_count']
        );
      }
      $this->setData($data);
    }

  }

  function getHeaders()
  {
    return array('Keywords', 'Count');
  }

  function getGoogleChartClass()
  {
    return 'google.visualization.ColumnChart';
  }

  function getJSData()
  {
    $js = 'var statData = new google.visualization.DataTable();
    statData.addColumn("string", "Keywords");
    statData.addColumn("number", "Count");

    statData.addRows([';

    $first = true;
    //Data
    foreach($this->getData() as $data){
      if($data[0] != null && !empty($data[0]) && $data[1] != null && !empty($data[1])) {
        if (!$first)
          $js .= ',';
        $first = false;
        $js .= '["' . $data[0] . '", ' . $data[1] . ']';
      }
    }

    $js .= ']);';

    $js .= 'var chartOptions = {
          title: "Keywords statistics",
          legend: { position: "bottom" }
        };';
    return $js;
  }


}