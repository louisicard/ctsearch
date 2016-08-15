<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 15/08/2016
 * Time: 20:15
 */

namespace CtSearchBundle\Controller;


use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class RestController extends Controller
{

  /**
   * @Route("/api/update", name="api-update")
   */
  public function updateAction(Request $request) {
    if ($request->get('mapping') != null) {
      if (count(explode('.', $request->get('mapping'))) == 2) {
        if ($request->get('doc_id') != null) {

          $index_name = explode('.', $request->get('mapping'))[0];
          $mapping_name = explode('.', $request->get('mapping'))[1];

          $res = IndexManager::getInstance()->getClient()->search(array(
            'index' => $index_name,
            'type' => $mapping_name,
            'body' => array(
              'query' => array(
                'ids' => array(
                  'values' => array($request->get('doc_id'))
                )
              )
            )
          ));

          if(isset($res['hits']['hits'][0]['_source'])) {

            $doc = $res['hits']['hits'][0]['_source'];

            if($request->get('data') !=null){
              $json = json_decode($request->get('data'), TRUE);
              foreach($json as $k => $v){
                $doc[$k] = $v;
              }
              $doc['_id'] = $request->get('doc_id');
              IndexManager::getInstance()->indexDocument($index_name, $mapping_name, $doc);
            }

            return new Response('{"status": "success"}', 200, array('Content-type' => 'application/json;charset=utf-8'));
          }
          else{
            return new Response('{"error": "doc_id yielded no result"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
          }
        } else {
          return new Response('{"error": "Missing doc_id parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
        }
      } else {
        return new Response('{"error": "Mapping does not exists"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
      }
    } else {
      return new Response('{"error": "Missing mapping parameter"}', 400, array('Content-type' => 'application/json;charset=utf-8'));
    }
  }
}