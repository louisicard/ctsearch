<?php

namespace CtSearch\ClientBundle\Controller;

use CtSearch\ClientBundle\Classes\CurlClient;
use CtSearch\ClientBundle\Classes\SearchContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
  public function searchAction(Request $request)
  {
    $serviceUrl = $request->get('serviceUrl');
    if ($request->get('mapping') == NULL) {
      return new Response('Missing mapping parameter', 400);
    }
    if ($request->get('sp_id') == NULL) {
      return new Response('Missing search page ID parameter', 400);
    }
    if ($serviceUrl == NULL) {
      $serviceUrl = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/search-api/v2';
    }

    $searchParams = $this->getSearchPageDefinition($request, $request->get('sp_id'));

    $highlight = '';
    if(isset($searchParams['results']['title']) && !empty($searchParams['results']['title'])){
      $highlight = $searchParams['results']['title'] . "|100|10|9999";
    }
    if(isset($searchParams['results']['excerp']) && !empty($searchParams['results']['excerp'])){
      if($highlight != '')
        $highlight .= ',';
      $highlight .= $searchParams['results']['excerp'] . "|200|3|300";
    }

    $facet_list = [];
    $sticky_facets = [];
    foreach($searchParams['facets'] as $facet){
      $facet_name = array_keys($facet)[0];
      $facet_list[] = $facet_name;
      if(isset($facet[$facet_name]['sticky']) && $facet[$facet_name]['sticky']){
        $sticky_facets[] = array_keys($facet)[0];
      }
    }

    $context = new SearchContext($request, $serviceUrl, $request->get('mapping'), implode(',', $facet_list), $searchParams['analyzer'], isset($searchParams['suggest']) ? implode(',', $searchParams['suggest']) : '', $highlight, implode(',', $sticky_facets));
    $context->setSize($searchParams['size']);

    if($request->get('sort') == null && isset($searchParams['sorting']['default']['field']) && isset($searchParams['sorting']['default']['order']) && $context->getQuery() == ''){
      $context->setSort($searchParams['sorting']['default']['field'] . ',' . $searchParams['sorting']['default']['order']);
    }

    $context->execute();

    $facets = array();
    foreach($context->getFacets() as $facet_name => $facet){
      if(isset($facet[$facet_name]))
        $facet = $facet[$facet_name];
      foreach($facet['buckets'] as $i => $bucket){
        $facet['buckets'][$i]['applied'] = $context->isFilterApplied($facet_name, $bucket['key']);
        $facet['buckets'][$i]['facet_link'] = $context->isFilterApplied($facet_name, $bucket['key']) ? $context->buildFilterRemovalUrl($facet_name, $bucket['key']) : $context->buildFilterUrl($facet_name, $bucket['key']);
      }
      if($facet['sum_other_doc_count'] > 0){
        $facet['see_more_link'] = $context->getFacetRaiseSizeUrl($facet_name);
      }
      $facets[$facet_name] = $facet;
    }

    foreach($facets as $i => $facet){
      if(!isset($facet['buckets']) || empty($facet['buckets'])){
        unset($facets[$i]);
      }
    }

    if(isset($searchParams['sorting']['fields'])){
      $sortingOptions = array();
      foreach($searchParams['sorting']['fields'] as $field){
        $key = array_keys($field)[0];
        $sortingOptions[] = array(
          'key' => $key,
          'label' => $field[$key] . ($key != '_score' ? ' (asc)' : ''),
          'active' => $context->getSort() == ($key . ',' . ($key != '_score' ? 'asc' : 'desc')),
          'url' => $context->getPagedUrl(0, $key . ',' . ($key != '_score' ? 'asc' : 'desc'))
        );
        if($key != '_score'){
          $sortingOptions[] = array(
            'key' => $key,
            'label' => $field[$key] . ($key != '_score' ? ' (desc)' : ''),
            'active' => $context->getSort() == ($key . ',desc'),
            'url' => $context->getPagedUrl(0, $key . ',desc')
          );
        }
      }
    }

    return $this->render('CtSearchClientBundle:Default:search.html.twig', array(
      'facets' => $facets,
      'sortingOptions' => isset($sortingOptions) ? $sortingOptions : array(),
      'mapping' => $request->get('mapping'),
      'context' => $context,
      'searchParams' => $searchParams,
      'searchPageId' => $request->get('sp_id'),
      'pager' => $this->getPager($context),
      'serviceUrl' => $serviceUrl,
    ));
  }

  private function getSearchPageDefinition(Request $request, $id){
    $curl = new CurlClient($request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/api/sp-definition/' . $id);
    $r = $curl->getResponse();
    if($r['code'] == 200){
      return json_decode($r['data'], TRUE);
    }
    else{
      return null;
    }
  }

  /**
   * @param SearchContext $searchContext
   * @return string
   */
  private function getPager($searchContext){
    $html = '<ul class="ctsearch-pager clearfix">';

    $currentPage = $searchContext->getFrom() / $searchContext->getSize() + 1;
    $previousPage = $currentPage > 1 ? $currentPage - 1 : null;
    $nextPage = ($currentPage + 1) <= ceil($searchContext->getTotal() / $searchContext->getSize()) ? $currentPage + 1 : null;

    if($previousPage != null){
      $html .= '<li class="page prev"><a href="' . $searchContext->getPagedUrl(($previousPage - 1) * $searchContext->getSize()) . '" class="ajax-link">Prev</a></li>';
    }
    $nbPages = 0;
    $i = $currentPage;
    $pages = '';
    while($nbPages <= 3 && $i > 0){
      $pages = '<li class="page' . ($i == $currentPage ? ' active' : '') . '"><a href="' . $searchContext->getPagedUrl(($i - 1) * $searchContext->getSize()) . '" class="ajax-link">' . $i . '</a></li>' . $pages;
      $nbPages++;
      $i--;
    }
    $i = $currentPage + 1;
    while($nbPages < 6 && $i <= ceil($searchContext->getTotal() / $searchContext->getSize())){
      $pages .= '<li class="page"><a href="' . $searchContext->getPagedUrl(($i - 1) * $searchContext->getSize()) . '" class="ajax-link">' . $i . '</a></li>';
      $nbPages++;
      $i++;
    }
    $html .= $pages;
    if($nextPage != null){
      $html .= '<li class="page next"><a href="' . $searchContext->getPagedUrl(($nextPage - 1) * $searchContext->getSize()) . '" class="ajax-link">Next</a></li>';
    }
    $html .= '</ul>';
    if($nbPages > 1)
      return $html;
    else
      return '';
  }

  public function moreLikeThisAction(Request $request){
    $mapping = $request->get('mapping');
    $doc_id = $request->get('doc_id');
    $fields = $request->get('fields');

    $searchParams = $this->getSearchPageDefinition($request, $request->get('sp_id'));

    $serviceUrl = $request->get('serviceUrl');
    if ($serviceUrl == NULL) {
      $serviceUrl = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/search-api/v2/more-like-this';
    }
    $serviceUrl .= '?mapping=' . urlencode($mapping) . '&doc_id=' . urlencode($doc_id) . '&fields=' . urlencode($fields);
    $curl = new CurlClient($serviceUrl);
    $r = $curl->getResponse();
    $html = '';
    if($r['code'] == 200){
      $data = json_decode($r['data'], TRUE);
      foreach($data as $result){
        $html .= '<div class="more-like-this-item">' . $this->renderView('CtSearchClientBundle:Default:result-item.html.twig', array(
          'result' => $result,
          'searchParams' => $searchParams
        )) . '</div>';
      }
    }
    return new Response($html, 200, array('Content-type' => 'text/html; charset=utf-8'));
  }
}