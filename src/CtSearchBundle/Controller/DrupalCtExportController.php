<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class DrupalCtExportController extends Controller {

  /**
   * @Route("/drupal/ctexport", name="drupal-ctexport")
   */
  public function indexAction(Request $request) {
    $xml = $request->get('xml');
    $id = $request->get('id');
    if ($xml == null || empty($xml)) {
      return new Response('{"error":"Missing xml parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    } elseif ($id == null || empty($id)) {
      return new Response('{"error":"Missing id parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    } else {
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $datasource = $indexManager->getDatasource($id, null);
      if($datasource == null || get_class($datasource) != 'CtSearchBundle\Datasource\DrupalCtExport'){
        return new Response('{"error":"No Drupal datasource found for this id"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
      else{
        $datasource->execute(array('xml' => $xml));
        return new Response('{"success":"OK"}', 200, array('Content-type' => 'application/json;charset=utf-8'));
      }
    }
  }

}
