<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 21/09/2016
 * Time: 17:03
 */

namespace CtSearch\ClientBundle\Classes;


use Symfony\Component\HttpFoundation\Request;

class SearchContext
{
  const CTSEARCH_STATUS_IDLE = 1;
  const CTSEARCH_STATUS_EXECUTED = 2;

  /**
   * @var string
   */
  private $query;

  /**
   * @var array
   */
  private $filters = array();

  /**
   * @var array
   */
  private $advancedFilters = array();

  /**
   * @var array
   */
  private $ids = array();

  /**
   * @var array
   */
  private $facetOptions = array();

  /**
   * @var array
   */
  private $results = array();

  /**
   * @var int
   */
  private $total = 0;

  /**
   * @var int
   */
  private $size = 10;

  /**
   * @var int
   */
  private $from = 0;

  /**
   * @var string
   */
  private $sort = '_score,desc';

  /**
   * @var array
   */
  private $facets = array();
  /**
   * @var int
   */
  private $status = SearchContext::CTSEARCH_STATUS_IDLE;

  /**
   * @var int
   */
  private $took = 0;

  /**
   * @var string
   */
  private $currentRequestUrl = "";

  /**
   * @var string
   */
  private $didYouMean = null;

  private $autopromote = array();

  private $serviceUrl;
  private $mapping;
  private $requestedFacets;
  private $stickyFacets;
  private $analyzer;
  private $suggest;
  private $highlights;

  /**
   * @var Request
   */
  private $request;


  /**
   * SearchContext constructor.
   * @param $args
   * @param Request $request
   */
  public function __construct($request, $serviceUrl, $mapping, $requestedFacets = NULL, $analyzer = NULL, $suggest = NULL, $highlights = NULL, $stickyFacets = NULL)
  {
    $this->serviceUrl = $serviceUrl;
    $this->mapping = $mapping;
    $this->request = $request;
    $this->requestedFacets = $requestedFacets;
    $this->analyzer = $analyzer;
    $this->suggest = $suggest;
    $this->highlights = $highlights;
    $this->stickyFacets = $stickyFacets;
    $this->build();
  }

  public function refresh(){
    if ($this->isNotEmpty()) {
      $this->execute();
    }
  }

  public function getDocumentById($id){
    $params = array(
      'mapping' => $this->mapping,
      'doc_id' => $id,
    );
    $url = $this->generateUrl($this->serviceUrl, $params);
    $response = $this->getResponse($url);
    if(isset($response['hits']['hits'][0])){
      return $response['hits']['hits'][0];
    }
    return null;
  }

  private function build(){
    $params = $this->request->query->all();
    if(isset($params['query'])){
      $this->query = trim($params['query']);
    }
    if(isset($params['filter'])){
      foreach($params['filter'] as $filter){
        $this->filters[] = $filter;
      }
    }
    if(isset($params['facetOptions'])){
      foreach($params['facetOptions'] as $option){
        $option_def = explode(',', $option);
        if(count($option_def) == 3){
          $this->facetOptions[$option_def[0]][$option_def[1]] = $option_def[2];
        }
      }
    }
    if(isset($params['ids'])){
      $this->ids = array_map('trim', explode(",", $params['ids']));
    }
    if(isset($params['size'])){
      $this->size = $params['size'];
    }
    if(isset($params['from'])){
      $this->from = $params['from'];
    }
    if(isset($params['sort'])){
      $this->sort = $params['sort'];
    }
    if(isset($params['qs_filter'])){
      foreach($params['qs_filter'] as $advFilter){
        preg_match('/(?P<field>[^=]*)="(?P<value>[^"]*)"/', $advFilter, $matches);
        if(isset($matches['field']) && isset($matches['value'])){
          $this->advancedFilters[] = array(
            'field' => $matches['field'],
            'value' => $matches['value'],
          );
        }
      }
    }
  }

  /**
   * @return bool
   */
  private function isNotEmpty(){
    return isset($this->query) && !empty($this->query) || !empty($this->filters) || !empty($this->ids);
  }

  public function execute($params = null){

    if($params == null) {
      $params = array(
        'mapping' => $this->mapping,
        'facets' => $this->requestedFacets,
        'sticky_facets' => $this->stickyFacets,
      );
      if ($this->analyzer != null) {
        $params['analyzer'] = $this->analyzer;
      }
      if ($this->suggest != null) {
        $params['suggest'] = $this->suggest;
      }
      if (isset($this->query) && !empty($this->query)) {
        $params['query'] = $this->query;
      }
      else{
        $params['query'] = '*';
      }
      if (!empty($this->filters)) {
        $params['filter'] = $this->filters;
      }
      if (!empty($this->ids)) {
        $params['ids'] = implode(",", $this->ids);
      }
      if (!empty($this->facetOptions)) {
        foreach ($this->facetOptions as $facet_id => $options) {
          foreach ($options as $k => $v) {
            $params['facetOptions'][] = $facet_id . ',' . $k . ',' . $v;
          }
        }
      }
      if (!empty($this->advancedFilters)) {
        $params['qs_filter'] = [];
        foreach ($this->advancedFilters as $filter) {
          $params['qs_filter'][] = $filter['field'] . '="' . $filter['value'] . '"';
        }
      }
      $params['size'] = $this->size;
      $params['from'] = $this->from;
      $params['sort'] = $this->sort;
      if ($this->highlights != null) {
        $params['highlights'] = $this->highlights;
      }
    }
    else{
      if(isset($params['size']))
        $this->size = $params['size'];
      if(isset($params['from']))
        $this->from = $params['from'];
      if(isset($params['sort']))
        $this->sort = $params['sort'];
      if(isset($params['ids']))
        $this->ids = $params['ids'];
    }


    $url = $this->generateUrl($this->serviceUrl, $params);
    $this->currentRequestUrl = $url;

    $response = $this->getResponse($url);
    if(isset($response['took'])){
      $this->took = $response['took'];
    }
    if (isset($response['hits']['hits'])) {
      $this->results = $response['hits']['hits'];
    }
    if (isset($response['hits']['total'])) {
      $this->total = $response['hits']['total'];
    }
    if (isset($response['aggregations'])) {
      $this->facets = $response['aggregations'];
    }
    if (isset($response['autopromote']) && isset($response['autopromote']['hits']['hits'])) {
      $this->autopromote = $response['autopromote']['hits']['hits'];
    }

    if(isset($response['suggest_ctsearch']) && count($response['suggest_ctsearch']) > 0){
      $this->setDidYouMean($response['suggest_ctsearch'][0]['text']);
    }
    $this->status = SearchContext::CTSEARCH_STATUS_EXECUTED;

  }

  private function getResponse($url){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if($code == 200) {
      return json_decode($r, true);
    }
    else{
      throw new \Exception("CtSearch response failed => code " . $code . ". Response is " . $r);
    }
  }

  public function buildFilterUrl($field, $value){
    $params = $this->request->query->all();
    unset($params['facetOptions']);
    $params['filter'][] = $field . '="' . $value . '"';
    return $this->generateUrl($this->request->getSchemeAndHttpHost() . $this->request->getRequestUri() . $this->request->getBaseUrl(), $params);
  }

  public function buildFilterRemovalUrl($field, $value){
    $params = $this->request->query->all();
    unset($params['filter']);
    unset($params['facetOptions']);
    foreach($this->filters as $filter) {
      if($filter != $field . '="' . $value . '"') {
        $params['filter'][] = $filter;
      }
    }
    return $this->generateUrl($this->request->getSchemeAndHttpHost() . $this->request->getRequestUri() . $this->request->getBaseUrl(), $params);
  }

  public function isFilterApplied($field, $value){
    return in_array($field . '="' . $value . '"', $this->filters);
  }

  public function getFacetRaiseSizeUrl($facet_id){
    $pace = 10;
    $new_size = isset($this->facetOptions[$facet_id]['size']) ? $this->facetOptions[$facet_id]['size'] + $pace : $pace * 2;
    $this->facetOptions[$facet_id]['size'] = $new_size;

    $params = $this->request->query->all();
    if(isset($params['facetOptions'])) {
      unset($params['facetOptions']);
    }
    foreach($this->facetOptions as $facet_id => $options) {
      foreach($options as $k => $v){
        $params['facetOptions'][] = $facet_id . ',' . $k . ',' . $v;
      }
    }
    return $this->generateUrl($this->request->getSchemeAndHttpHost() . $this->request->getRequestUri() . $this->request->getBaseUrl(), $params);
  }

  public function getPagedUrl($from = null, $sort = null){
    $params = $this->request->query->all();
    if($from !== null) {
      $params['from'] = $from;
    }
    if($sort !== null) {
      $params['sort'] = $sort;
    }
    return $this->generateUrl($this->request->getSchemeAndHttpHost() . $this->request->getRequestUri() . $this->request->getBaseUrl(), $params);
  }

  /**
   * @return string
   */
  public function getQuery()
  {
    return isset($this->query) ? ($this->query != '*' ? $this->query : '') : null;
  }

  /**
   * @param string $query
   */
  public function setQuery($query)
  {
    $this->query = $query;
  }

  /**
   * @return array
   */
  public function getResults()
  {
    return $this->results;
  }

  /**
   * @param array $results
   */
  public function setResults($results)
  {
    $this->results = $results;
  }

  /**
   * @return array
   */
  public function getFacets()
  {
    return $this->facets;
  }

  /**
   * @param array $facets
   */
  public function setFacets($facets)
  {
    $this->facets = $facets;
  }

  /**
   * @return int
   */
  public function getStatus()
  {
    return $this->status;
  }

  /**
   * @param int $status
   */
  public function setStatus($status)
  {
    $this->status = $status;
  }

  /**
   * @return array
   */
  public function getFilters()
  {
    return $this->filters;
  }

  /**
   * @return array
   */
  public function getAdvancedFilters()
  {
    return $this->advancedFilters;
  }

  /**
   * @return int
   */
  public function getTotal()
  {
    return $this->total;
  }

  /**
   * @param int $total
   */
  public function setTotal($total)
  {
    $this->total = $total;
  }

  /**
   * @return int
   */
  public function getSize()
  {
    return $this->size;
  }

  /**
   * @param int $size
   */
  public function setSize($size)
  {
    $this->size = $size;
  }

  /**
   * @return int
   */
  public function getFrom()
  {
    return $this->from;
  }

  /**
   * @param int $from
   */
  public function setFrom($from)
  {
    $this->from = $from;
  }

  /**
   * @return string
   */
  public function getSort()
  {
    return $this->sort;
  }

  /**
   * @param string $sort
   */
  public function setSort($sort)
  {
    $this->sort = $sort;
  }

  /**
   * @return string
   */
  public function getCurrentRequestUrl(){
    return $this->currentRequestUrl;
  }

  /**
   * @return string
   */
  public function getDidYouMean()
  {
    return $this->didYouMean;
  }

  /**
   * @param string $didYouMean
   */
  public function setDidYouMean($didYouMean)
  {
    $this->didYouMean = $didYouMean;
  }

  /**
   * @return array
   */
  public function getFacetOptions()
  {
    return $this->facetOptions;
  }

  /**
   * @param array $facetOptions
   */
  public function setFacetOptions($facetOptions)
  {
    $this->facetOptions = $facetOptions;
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
   * @return null
   */
  public function getRequestedFacets()
  {
    return $this->requestedFacets;
  }

  /**
   * @param null $requestedFacets
   */
  public function setRequestedFacets($requestedFacets)
  {
    $this->requestedFacets = $requestedFacets;
  }

  /**
   * @return null
   */
  public function getAnalyzer()
  {
    return $this->analyzer;
  }

  /**
   * @param null $analyzer
   */
  public function setAnalyzer($analyzer)
  {
    $this->analyzer = $analyzer;
  }

  /**
   * @return null
   */
  public function getSuggest()
  {
    return $this->suggest;
  }

  /**
   * @param null $suggest
   */
  public function setSuggest($suggest)
  {
    $this->suggest = $suggest;
  }

  /**
   * @return null
   */
  public function getHighlights()
  {
    return $this->highlights;
  }

  /**
   * @param null $highlights
   */
  public function setHighlights($highlights)
  {
    $this->highlights = $highlights;
  }

  /**
   * @return array
   */
  public function getAutopromote()
  {
    return $this->autopromote;
  }

  /**
   * @return int
   */
  public function getTook()
  {
    return $this->took;
  }

  /**
   * @param int $took
   */
  public function setTook($took)
  {
    $this->took = $took;
  }


  private function generateUrl($url, $args){
    $url_parts = explode('?', $url);
    if(count($url_parts) > 0){
      $url = $url_parts[0];
    }
    $qs = '';
    foreach($args as $k => $v){
      if($v != NULL) {
        if ($qs != '') {
          $qs .= '&';
        }
        if (is_array($v)) {
          $first = true;
          foreach ($v as $vv) {
            if (!$first) {
              $qs .= '&';
            }
            $qs .= $k . '[]=' . urlencode($vv);
            $first = false;
          }
        } else {
          $qs .= $k . '=' . urlencode($v);
        }
      }
    }

    return $url . '?' . $qs;
  }

}