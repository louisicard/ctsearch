<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class DrupalCtExportController extends Controller {

  /**
   * @Route("/drupal/ctexport", name="drupal-ctexport")
   * @Method({"PUT", "DELETE"})
   */
  public function indexAction(Request $request) {
    $xml = $request->get('xml');
    $id = $request->get('id');
    $item_id = $request->get('item_id');
    $target_mapping = $request->get('target_mapping');
    if ($id == null || empty($id)) {
      return new Response('{"error":"Missing id parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    } else {
      $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
      $datasource = $indexManager->getDatasource($id, null);
      if($datasource == null || get_class($datasource) != 'CtSearchBundle\Datasource\DrupalCtExport'){
        return new Response('{"error":"No Drupal datasource found for this id"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
      else{
        
        $method = $request->getMethod();
        switch ($method) {
          case 'PUT':
            if ($xml == null || empty($xml)) {
              return new Response('{"error":"Missing xml parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
            }
            $datasource->execute(array('xml' => $xml));
            break;
          case 'DELETE':
            if ($item_id == null || empty($item_id)) {
              return new Response('{"error":"Missing item_id parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
            }
            if ($target_mapping == null || empty($target_mapping)) {
              return new Response('{"error":"Missing target_mapping parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
            }
            
            $indexManager = new IndexManager($this->container->getParameter('ct_search.es_url'));
            
            $indexManager->deleteByQuery(explode('.', $target_mapping)[0], explode('.', $target_mapping)[1], json_decode('{"query":{"ids":{"values":["' . $item_id . '"]}}}', true));
            
            break;
        }
        
        return new Response('{"success":"OK"}', 200, array('Content-type' => 'application/json;charset=utf-8'));
      }
    }
  }

}
