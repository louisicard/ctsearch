<?php

namespace CtSearchBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use \CtSearchBundle\CtSearchBundle;
use CtSearchBundle\Classes\IndexManager;
use \CtSearchBundle\Classes\Processor;
use \Symfony\Component\HttpFoundation\Response;

class WebCrawlerController extends CtSearchController {

  /**
   * @Route("/webcrawler-response", name="webcrawler-response")
   */
  public function webCrawlerResponseAction(Request $request) {
    if($request->get('datasourceId') != null){
      $datasource = IndexManager::getInstance()->getDatasource($request->get('datasourceId'), $this);
      $datasource->handleDataFromCallback(array(
        'title' => $request->get('title') != null ? $request->get('title') : '',
        'html' => $request->get('html') != null ? $request->get('html') : '',
        'url' => $request->get('url') != null ? $request->get('url') : '',
      ));
      return new Response(json_encode(array('Status' => 'OK')), 200, array('Content-type' => 'text/html; charset=utf-8'));
    }
    else{
      return new Response(json_encode(array('Error' => 'No datasource provided')), 400, array('Content-type' => 'application/json'));
    }
  }


}
