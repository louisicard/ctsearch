<?php

namespace CtSearchBundle\Classes;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use CtSearchBundle\Classes\Mapping;
use CtSearchBundle\Datasource\Datasource;

class IndexManager {

  /**
   * @var Client
   */
  private $client;
  private $esUrl;

  /**
   * 
   * @param Elasticsearch\Client $client
   */
  function __construct($esUrl) {
    $this->esUrl = $esUrl;
  }

  /**
   * 
   * @return Client
   */
  function getClient() {
    if (!isset($this->client)) {
      $clientBuilder = new ClientBuilder();
      $clientBuilder->setHosts(array($this->esUrl));
      $this->client = $clientBuilder->build();
    }
    return $this->client;
  }

  function getElasticInfo() {
    $info = array();
    $stats = $this->getClient()->indices()->stats();
    foreach ($stats['indices'] as $index_name => $stat) {
      $info[$index_name] = array(
        'count' => $stat['total']['docs']['count'] - $stat['total']['docs']['deleted'],
        'size' => round($stat['total']['store']['size_in_bytes'] / 1024 / 1024, 2) . ' MB',
      );
      $mappings = $this->getClient()->indices()->getMapping(array('index' => $index_name));
      foreach ($mappings[$index_name]['mappings'] as $mapping => $properties) {
        $info[$index_name]['mappings'][] = array(
          'name' => $mapping,
          'field_count' => count($properties['properties']),
        );
      }
    }
    return $info;
  }

  /**
   * 
   * @param Index $index
   */
  function createIndex($index) {
    $settings = json_decode($index->getSettings(), true);
    if (isset($settings['creation_date']))
      unset($settings['creation_date']);
    if (isset($settings['version']))
      unset($settings['version']);
    if (isset($settings['uuid']))
      unset($settings['uuid']);
    $params = array(
      'index' => $index->getIndexName(),
    );
    if (count($settings) > 0) {
      $params['body'] = array(
        'settings' => $settings,
      );
    }
    $this->getClient()->indices()->create($params);
  }

  /**
   * 
   * @param Index $index
   */
  function updateIndex($index) {
    $settings = json_decode($index->getSettings(), true);
    if (isset($settings['creation_date']))
      unset($settings['creation_date']);
    if (isset($settings['version']))
      unset($settings['version']);
    if (isset($settings['uuid']))
      unset($settings['uuid']);
    if (isset($settings['number_of_shards']))
      unset($settings['number_of_shards']);
    if (isset($settings['analysis']))
      unset($settings['analysis']);
    if (count($settings) > 0) {
      $this->getClient()->indices()->putSettings(array(
        'index' => $index->getIndexName(),
        'body' => array(
          'settings' => $settings,
        ),
      ));
    }
  }

  /**
   * 
   * @param Index $index
   */
  function getIndex($indexName) {
    try {
      $settings = $this->getClient()->indices()->getSettings(array(
        'index' => $indexName,
      ));
      $settings = $settings[$indexName]['settings']['index'];
      return new Index($indexName, json_encode($settings, JSON_PRETTY_PRINT));
    } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $ex) {
      return null;
    }
  }

  /**
   * 
   * @param Index $index
   */
  function deleteIndex($index) {
    $this->getClient()->indices()->delete(array(
      'index' => $index->getIndexName(),
    ));
  }

  /**
   * 
   * @param string $indexName
   * @param string $mappingName
   * @return Mapping
   */
  function getMapping($indexName, $mappingName) {
    try {
      $mapping = $this->getClient()->indices()->getMapping(array(
        'index' => $indexName,
        'type' => $mappingName,
      ));
      if (isset($mapping[$indexName]['mappings'][$mappingName]['properties']))
        return new Mapping($indexName, $mappingName, json_encode($mapping[$indexName]['mappings'][$mappingName]['properties'], JSON_PRETTY_PRINT));
      else
        return null;
    } catch (\Exception $ex) {
      return null;
    }
  }

  /**
   * 
   * @param Mapping $mapping
   */
  function updateMapping($mapping) {
    if ($mapping->getWipeData()) {
      $this->getClient()->deleteByQuery(array(
        'index' => $mapping->getIndexName(),
        'type' => $mapping->getMappingName(),
        'body' => array(
          'query' => array(
            'match_all' => array()
          )
        )
      ));
    }
    $this->getClient()->indices()->putMapping(array(
      'index' => $mapping->getIndexName(),
      'type' => $mapping->getMappingName(),
      'body' => array(
        'properties' => json_decode($mapping->getMappingDefinition(), true),
      ),
    ));
  }

  /**
   * 
   * @param string $indexName
   * @return string[]
   */
  function getAnalyzers($indexName) {
    $analyzers = array('standard', 'simple', 'whitespace', 'stop', 'keyword', 'pattern', 'language', 'snowball');
    $settings = $this->getClient()->indices()->getSettings(array(
      'index' => $indexName,
    ));
    if (isset($settings[$indexName]['settings']['index']['analysis']['analyzer'])) {
      foreach ($settings[$indexName]['settings']['index']['analysis']['analyzer'] as $analyzer => $definition) {
        $analyzers[] = $analyzer;
      }
    }
    return $analyzers;
  }

  /**
   * 
   * @return string[]
   */
  function getFieldTypes() {
    $types = array('string', 'integer', 'long', 'float', 'double', 'boolean', 'date', 'ip', 'geo_point');
    asort($types);
    return $types;
  }

  /**
   * 
   * @return string[]
   */
  function getDateFormats() {
    return array('basic_date', 'basic_date_time', 'basic_date_time_no_millis', 'basic_ordinal_date', 'basic_ordinal_date_time', 'basic_ordinal_date_time_no_millis', 'basic_time', 'basic_time_no_millis', 'basic_t_time', 'basic_t_time_no_millis', 'basic_week_date', 'basic_week_date_time', 'basic_week_date_time_no_millis', 'date', 'date_hour', 'date_hour_minute', 'date_hour_minute_second', 'date_hour_minute_second_fraction', 'date_hour_minute_second_millis', 'date_optional_time', 'date_time', 'date_time_no_millis', 'hour', 'hour_minute', 'hour_minute_second', 'hour_minute_second_fraction', 'hour_minute_second_millis', 'ordinal_date', 'ordinal_date_time', 'ordinal_date_time_no_millis', 'time', 'time_no_millis', 't_time', 't_time_no_millis', 'week_date', 'week_date_time', 'weekDateTimeNoMillis', 'week_year', 'weekyearWeek', 'weekyearWeekDay', 'year', 'year_month', 'year_month_day');
  }

  function getDatasources($controller) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'datasource',
          'size' => 9999,
          'sort' => 'name:asc'
        ));
        $datasources = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $datasource = new $hit['_source']['class']($hit['_source']['name'], $controller);
            $datasource->initFromSettings(unserialize($hit['_source']['definition']));
            $datasource->setId($hit['_id']);
            $datasources[$hit['_id']] = $datasource;
          }
        }
        return $datasources;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  /**
   * 
   * @param string $id
   * @param \Symfony\Bundle\FrameworkBundle\Controller\Controller $controller
   * @return Datasource
   */
  function getDatasource($id, $controller) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'datasource',
          'body' => array(
            'query' => array(
              'match' => array(
                '_id' => $id,
              )
            )
          )
        ));
        if (isset($r['hits']['hits']) && count($r['hits']['hits']) > 0) {
          $hit = $r['hits']['hits'][0];
          $datasource = new $hit['_source']['class']($hit['_source']['name'], $controller);
          $datasource->initFromSettings(unserialize($hit['_source']['definition']));
          $datasource->setId($id);
          return $datasource;
        }
        return null;
      } catch (\Exception $ex) {
        return null;
      }
    } else {
      return null;
    }
  }

  function getDatasourceTypes() {
    $classes = get_declared_classes();
    $types = array();
    foreach ($classes as $class) {
      if (is_subclass_of($class, 'CtSearchBundle\Datasource\Datasource')) {
        $instance = new $class();
        $types[$class] = $instance->getDatasourceDisplayName();
      }
    }
    return $types;
  }

  function getFilterTypes() {
    $classes = get_declared_classes();
    $types = array();
    foreach ($classes as $class) {
      if (is_subclass_of($class, 'CtSearchBundle\Processor\ProcessorFilter')) {
        $instance = new $class();
        $types[str_replace("\\", '\\\\', $class)] = $instance->getDisplayName();
      }
    }
    return $types;
  }

  /**
   * 
   * @param Datasource $datasource
   * @param string $id
   * @return type
   */
  public function saveDatasource($datasource, $id = null) {
    if ($this->getIndex('.ctsearch') == null) {
      $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_index_settings.json');
      $this->createIndex(new Index('.ctsearch', $settingsDefinition));
    }
    if ($this->getMapping('.ctsearch', 'datasource') == null) {
      $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_datasource_definition.json');
      $this->updateMapping(new Mapping('.ctsearch', 'datasource', $mappingDefinition));
    }
    if ($this->getMapping('.ctsearch', 'logs') == null) {
      $logsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_logs_definition.json');
      $this->updateMapping(new Mapping('.ctsearch', 'logs', $logsDefinition));
    }
    $params = array(
      'index' => '.ctsearch',
      'type' => 'datasource',
      'body' => array(
        'class' => get_class($datasource),
        'definition' => serialize($datasource->getSettings()),
        'last_execution' => date('Y-m-d\TH:i:s.000O'),
        'name' => $datasource->getName()
      )
    );
    if ($id != null) {
      $params['id'] = $id;
    }
    $r = $this->getClient()->index($params);
    $this->getClient()->indices()->flush();
    return $r;
  }

  /**
   * 
   * @param string $id
   * @return type
   */
  public function deleteDatasource($id) {
    $this->getClient()->delete(array(
      'index' => '.ctsearch',
      'type' => 'datasource',
      'id' => $id,
      )
    );
    $this->getClient()->indices()->flush();
  }

  /**
   * 
   * @param Processor $processor
   * @param string $id
   * @return array
   */
  public function saveProcessor($processor, $id = null) {
    if ($this->getIndex('.ctsearch') == null) {
      $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_index_settings.json');
      $this->createIndex(new Index('.ctsearch', $settingsDefinition));
    }
    if ($this->getMapping('.ctsearch', 'processor') == null) {
      $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_processor_definition.json');
      $this->updateMapping(new Mapping('.ctsearch', 'processor', $mappingDefinition));
    }
    $datasource = $this->getDatasource($processor->getDatasourceId(), null);
    $params = array(
      'index' => '.ctsearch',
      'type' => 'processor',
      'body' => array(
        'datasource' => $processor->getDatasourceId(),
        'datasource_name' => $datasource->getName(),
        'target' => $processor->getTarget(),
        'definition' => serialize(json_decode($processor->getDefinition(), true))
      )
    );
    if ($id != null) {
      $params['id'] = $id;
    }
    $r = $this->getClient()->index($params);
    $this->getClient()->indices()->flush();
    return $r;
  }

  function getRawProcessors() {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'processor',
          'size' => 9999,
          'sort' => 'datasource_name:asc,target:asc'
        ));
        $processors = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $processors[] = array(
              'id' => $hit['_id'],
              'datasource_id' => $hit['_source']['datasource'],
              'datasource_name' => $hit['_source']['datasource_name'],
              'target' => $hit['_source']['target'],
              'definition' => json_encode(unserialize($hit['_source']['definition']), JSON_PRETTY_PRINT),
            );
          }
        }
        return $processors;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  function getRawProcessorsByDatasource($datasourceId) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'processor',
          'size' => 9999,
          'sort' => 'datasource_name:asc,target:asc',
          'body' => array(
            'query' => array(
              'match' => array(
                'datasource' => $datasourceId,
              )
            )
          )
        ));
        $processors = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $processors[] = array(
              'id' => $hit['_id'],
              'datasource_id' => $hit['_source']['datasource'],
              'datasource_name' => $hit['_source']['datasource_name'],
              'target' => $hit['_source']['target'],
              'definition' => json_encode(unserialize($hit['_source']['definition']), JSON_PRETTY_PRINT),
            );
          }
        }
        return $processors;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  /**
   * 
   * @param string $id
   * @return Processor
   */
  function getProcessor($id) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'processor',
          'body' => array(
            'query' => array(
              'match' => array(
                '_id' => $id,
              )
            )
          )
        ));
        if (isset($r['hits']['hits']) && count($r['hits']['hits']) > 0) {
          $hit = $r['hits']['hits'][0];
          $processor = new Processor($hit['_source']['datasource'], $hit['_source']['target'], json_encode(unserialize($hit['_source']['definition']), JSON_PRETTY_PRINT));
          return $processor;
        }
        return null;
      } catch (\Exception $ex) {
        return null;
      }
    } else {
      return null;
    }
  }

  /**
   * 
   * @param string $id
   * @return type
   */
  public function deleteProcessor($id) {
    $this->getClient()->delete(array(
      'index' => '.ctsearch',
      'type' => 'processor',
      'id' => $id,
      )
    );
    $this->getClient()->indices()->flush();
  }

  /**
   * 
   * @param string $indexName
   * @param string $mappingName
   * @param array $document
   * @return array
   */
  public function indexDocument($indexName, $mappingName, $document, $flush = true) {
    $id = null;
    if (isset($document['_id'])) {
      $id = $document['_id'];
      unset($document['_id']);
    }
    $params = array(
      'index' => $indexName,
      'type' => $mappingName,
      'body' => $document,
    );
    if ($id != null) {
      $params['id'] = $id;
    }
    $r = $this->getClient()->index($params);
    if ($flush) {
      $this->getClient()->indices()->flush();
    }
    return $r;
  }

  /**
   * 
   * @return \CtSearchBundle\Classes\SearchPage[]
   */
  function getSearchPages() {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'search_page',
          'size' => 9999,
          'sort' => 'name:asc'
        ));
        $searchPages = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $searchPages[] = new SearchPage($hit['_source']['name'], $hit['_source']['index_name'], unserialize($hit['_source']['definition']), unserialize($hit['_source']['config']), $hit['_id']);
          }
        }
        return $searchPages;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  /**
   * 
   * @return \CtSearchBundle\Classes\SearchAPI[]
   */
  function getSearchAPIs() {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'search_api',
          'size' => 9999,
          'sort' => 'index_name:asc'
        ));
        $searchAPIs = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $searchAPIs[] = new SearchAPI($hit['_source']['mapping_name'], unserialize($hit['_source']['definition']), $hit['_id']);
          }
        }
        return $searchAPIs;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  /**
   * 
   * @param string $id
   * @return SearchPage
   */
  function getSearchPage($id) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'search_page',
          'body' => array(
            'query' => array(
              'match' => array(
                '_id' => $id,
              )
            )
          )
        ));
        if (isset($r['hits']['hits']) && count($r['hits']['hits']) > 0) {
          $hit = $r['hits']['hits'][0];
          return new SearchPage($hit['_source']['name'], $hit['_source']['index_name'], unserialize($hit['_source']['definition']), unserialize($hit['_source']['config']), $hit['_id']);
        }
        return null;
      } catch (\Exception $ex) {
        return null;
      }
    } else {
      return null;
    }
  }

  /**
   * 
   * @param SearchPage $searchPage
   * @return array
   */
  public function saveSearchPage($searchPage) {
    if ($this->getIndex('.ctsearch') == null) {
      $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_index_settings.json');
      $this->createIndex(new Index('.ctsearch', $settingsDefinition));
    }
    if ($this->getMapping('.ctsearch', 'search_page') == null) {
      $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_search_page_definition.json');
      $this->updateMapping(new Mapping('.ctsearch', 'search_page', $mappingDefinition));
    }
    $params = array(
      'index' => '.ctsearch',
      'type' => 'search_page',
      'body' => array(
        'name' => $searchPage->getName(),
        'index_name' => $searchPage->getIndexName(),
        'definition' => serialize(json_decode($searchPage->getDefinition(), true)),
        'config' => serialize(json_decode($searchPage->getConfig(), true))
      )
    );
    if ($searchPage->getId() != null) {
      $params['id'] = $searchPage->getId();
    }
    $r = $this->getClient()->index($params);
    $this->getClient()->indices()->flush();
    return $r;
  }

  /**
   * 
   * @param string $id
   * @return type
   */
  public function deleteSearchPage($id) {
    $this->getClient()->delete(array(
      'index' => '.ctsearch',
      'type' => 'search_page',
      'id' => $id,
      )
    );
    $this->getClient()->indices()->flush();
  }

  public function search($indexName, $json, $from = 0, $size = 20, $type = null) {
    $body = json_decode($json, true);
    $this->sanitizeGlobalAgg($body);
    $params = array(
      'index' => $indexName,
      'body' => $body,
    );

    if ($type != null)
      $params['type'] = $type;
    $params['body']['from'] = $from;
    $params['body']['size'] = $size;
    try {
      return $this->getClient()->search($params);
    } catch (\Exception $ex) {
      return array();
    }
  }

  private function sanitizeGlobalAgg(&$array) { //Bug fix form empty queries in global aggregations
    if ($array != null) {
      foreach ($array as $k => $v) {
        if ($k == 'global' && empty($v))
          $array[$k] = new \stdClass();
        elseif (is_array($v))
          $this->sanitizeGlobalAgg($array[$k]);
      }
    }
  }

  public function analyze($indexName, $analyzer, $text) {
    return $this->getClient()->indices()->analyze(array(
        'index' => $indexName,
        'analyzer' => $analyzer,
        'text' => $text,
    ));
  }

  public function log($type, $message, $object) {
    $this->indexDocument('.ctsearch', 'logs', array(
      'type' => $type,
      'message' => $message,
      'object' => json_encode($object),
      'date' => date('Y-m-d\TH:i:s'),
    ));
  }

  /**
   * 
   * @return \CtSearchBundle\Classes\MatchingList[]
   */
  function getMatchingLists() {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'matching_list',
          'size' => 9999,
          'sort' => 'name:asc'
        ));
        $matchingLists = array();
        if (isset($r['hits']['hits'])) {
          foreach ($r['hits']['hits'] as $hit) {
            $matchingLists[] = new MatchingList($hit['_source']['name'], unserialize($hit['_source']['list']), $hit['_id']);
          }
        }
        return $matchingLists;
      } catch (\Exception $ex) {
        return array();
      }
    } else {
      return array();
    }
  }

  /**
   * 
   * @param string $id
   * @return \CtSearchBundle\Classes\MatchingList
   */
  function getMatchingList($id) {
    if ($this->getIndex('.ctsearch') != null) {
      try {
        $r = $this->getClient()->search(array(
          'index' => '.ctsearch',
          'type' => 'matching_list',
          'body' => array(
            'query' => array(
              'match' => array(
                '_id' => $id,
              )
            )
          )
        ));
        if (isset($r['hits']['hits']) && count($r['hits']['hits']) > 0) {
          $hit = $r['hits']['hits'][0];
          return new MatchingList($hit['_source']['name'], unserialize($hit['_source']['list']), $hit['_id']);
        }
        return null;
      } catch (\Exception $ex) {
        return null;
      }
    } else {
      return null;
    }
  }

  /**
   * 
   * @param MatchingList $matchingList
   * @return array
   */
  public function saveMatchingList($matchingList) {
    if ($this->getIndex('.ctsearch') == null) {
      $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_index_settings.json');
      $this->createIndex(new Index('.ctsearch', $settingsDefinition));
    }
    if ($this->getMapping('.ctsearch', 'search_page') == null) {
      $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_matching_list_definition.json');
      $this->updateMapping(new Mapping('.ctsearch', 'matching_list', $mappingDefinition));
    }
    $params = array(
      'index' => '.ctsearch',
      'type' => 'matching_list',
      'body' => array(
        'name' => $matchingList->getName(),
        'list' => serialize(json_decode($matchingList->getList(), true))
      )
    );
    if ($matchingList->getId() != null) {
      $params['id'] = $matchingList->getId();
    }
    $r = $this->getClient()->index($params);
    $this->getClient()->indices()->flush();
    return $r;
  }

  /**
   * 
   * @param string $id
   * @return type
   */
  public function deleteMatchingList($id) {
    $this->getClient()->delete(array(
      'index' => '.ctsearch',
      'type' => 'matching_list',
      'id' => $id,
      )
    );
    $this->getClient()->indices()->flush();
  }

  public function getACSettings($indexName) {
    if ($this->getIndex('.ctsearch') == null) {
      $settingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_index_settings.json');
      $this->createIndex(new Index('.ctsearch', $settingsDefinition));
    }
    if ($this->getMapping('.ctsearch', 'ac_settings') == null) {
      $acSettingsDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_ac_settings_defintion.json');
      $this->updateMapping(new Mapping('.ctsearch', 'ac_settings', $acSettingsDefinition));
    }
    try {
      $r = $this->getClient()->search(array(
        'index' => '.ctsearch',
        'type' => 'ac_settings',
        'body' => array(
          'query' => array(
            'match' => array(
              'ct_index_name' => $indexName,
            )
          )
        )
      ));
      if (isset($r['hits']['hits']) && count($r['hits']['hits']) > 0) {
        $hit = $r['hits']['hits'][0];
        return array(
          'index_name' => $hit['_source']['index_name'],
          'fields' => json_decode($hit['_source']['fields'], true),
          'analyzer_filters' => json_decode($hit['_source']['analyzer_filters'], true),
        );
      }
      return null;
    } catch (\Exception $ex) {
      return null;
    }
  }

  public function getAvailableFilters($indexName) {
    $infos = $this->getClient()->indices()->getSettings(array(
      'index' => $indexName
    ));
    $filters = array();
    if (isset($infos[$indexName]['settings']['index']['analysis']['filter'])) {
      foreach ($infos[$indexName]['settings']['index']['analysis']['filter'] as $name => $filter) {
        if (!in_array($name, $filters)) {
          $filters[] = $name;
        }
      }
    }
    if (isset($infos[$indexName]['settings']['index']['analysis']['analyzer'])) {
      foreach ($infos[$indexName]['settings']['index']['analysis']['analyzer'] as $k => $analyzer) {
        if (isset($analyzer['filter'])) {
          foreach ($analyzer['filter'] as $filter) {
            if (!in_array($filter, $filters)) {
              $filters[] = $filter;
            }
          }
        }
      }
    }
    return $filters;
  }

  public function saveACSettings($settings) {
    $this->getACSettings($settings['index_name']);
    $this->getClient()->deleteByQuery(array(
      'index' => '.ctsearch',
      'type' => 'ac_settings',
      'body' => array(
        'query' => array(
          'match' => array(
            'ct_index_name' => $settings['index_name'],
          )
        )
      )
    ));
    $this->indexDocument('.ctsearch', 'ac_settings', array(
      'ct_index_name' => $settings['index_name'],
      'fields' => json_encode($settings['fields']),
      'analyzer_filters' => json_encode($settings['analyzer_filters']),
    ));
    $this->getClient()->indices()->close(array(
      'index' => $settings['index_name']
    ));
    $this->getClient()->indices()->putSettings(array(
      'index' => $settings['index_name'],
      'body' => array(
        'analysis' => array(
          'filter' => array(
            'ctsearch_ac_shingle' => array(
              'max_shingle_size' => '5',
              'min_shingle_size' => '2',
              'output_unigrams_if_no_shingles' => 'true',
              'type' => 'shingle',
              'output_unigrams' => 'false',
              'filler_token' => ''
            )
          ),
          'analyzer' => array(
            'ctsearch_ac' => array(
              'type' => 'custom',
              'tokenizer' => 'standard',
              'filter' => array_values(array_unique(array_merge($settings['analyzer_filters'], array('ctsearch_ac_shingle', 'trim', 'unique'))))
            ),
            'ctsearch_ac_transliterate' => array(
              'type' => 'custom',
              'tokenizer' => 'standard',
              'filter' => array('asciifolding')
            )
          )
        )
      )
    ));
    $this->getClient()->indices()->open(array(
      'index' => $settings['index_name']
    ));
  }

  public function feedAutocomplete($indexName, $text) {
    $infos = $this->getClient()->indices()->getSettings(array(
      'index' => $indexName
    ));
    if (isset($infos[$indexName]['settings']['index']['analysis']['analyzer']['ctsearch_ac'])) {
      $shingles_raw = $this->getClient()->indices()->analyze(array(
        'index' => $indexName,
        'analyzer' => 'ctsearch_ac',
        'text' => $text
      ));
      $shingles = array();
      foreach ($shingles_raw['tokens'] as $shingle) {
        $shingle = preg_replace('/\s+/', ' ', trim($shingle['token']));
        if (!in_array($shingle, $shingles)) {
          $shingles[] = $shingle;
        }
      }
      if ($this->getMapping($indexName, '.ctsearch-autocomplete') == null) {
        $mappingDefinition = file_get_contents(__DIR__ . '/../Resources/ctsearch_ac_mapping_defintion.json');
        $this->updateMapping(new Mapping($indexName, '.ctsearch-autocomplete', $mappingDefinition));
      }
      foreach ($shingles as $shingle) {
        $res = $this->search($indexName, json_encode(array(
          'query' => array(
            'bool' => array(
              'must' => array(
                'type' => array(
                  'value' => '.ctsearch-autocomplete'
                )
              ),
              'must' => array(
                'term' => array(
                  'text' => $shingle
                )
              )
            )
          )
        )));
        if ($res['hits']['total'] == 0) {
          $r = $this->indexDocument($indexName, '.ctsearch-autocomplete', array(
            '_id' => $shingle,
            'text' => $shingle,
            'text_transliterate' => $shingle,
            'counter' => 1,
            ), false);
        } else {
          $r = $this->indexDocument($indexName, '.ctsearch-autocomplete', array(
            '_id' => $shingle,
            'text' => $shingle,
            'text_transliterate' => $shingle,
            'counter' => $res['hits']['hits'][0]['_source']['counter'] + 1,
            ), false);
        }
      }
      $this->getClient()->indices()->flush(array(
        'index' => $indexName
      ));
    }
  }

}
