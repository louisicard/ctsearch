<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 20/05/2016
 * Time: 12:08
 */

namespace CtSearchBundle\Controller;


use CtSearchBundle\Classes\IndexManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class JSSearchController extends Controller
{
  /**
   * @Route("/searchapi/searchapi.js", name="js_search_api")
   */
  public function getJSAction(Request $request){
    $env = $this->container->get( 'kernel' )->getEnvironment();
    $js = '(function(){
      var CtSearch = function(){
        this.serviceUrl = "//' . $_SERVER['HTTP_HOST'] . ($env != 'prod' ? '/app_' . $env . '.php' : '') . '/searchapi/service";

        this.getXMLHTTPRequest = function() {
          var xhr = null;

          if (window.XMLHttpRequest || window.ActiveXObject) {
            if (window.ActiveXObject) {
              try {
                xhr = new ActiveXObject("Msxml2.XMLHTTP");
              } catch(e) {
                xhr = new ActiveXObject("Microsoft.XMLHTTP");
              }
            } else {
              xhr = new XMLHttpRequest();
            }
          } else {
            return null;
          }

          return xhr;
        }

        this.search = function(index, query, callback){
          var json = JSON.stringify(query);
          var xhr = this.getXMLHTTPRequest();
          xhr.open("POST", this.serviceUrl, true);
          xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
          xhr.onreadystatechange = function(){
            if(xhr.readyState == 4 && xhr.status == 200){
              eval("var xhrResponse = " + xhr.responseText);
              callback(xhrResponse);
            }
          };
          xhr.send("query=" + encodeURIComponent(json) + "&index=" + encodeURIComponent(index));
        };
      };

      window["CtSearch"] = new CtSearch();
    })();';

    return new Response($js, 200, array(
      'Content-type' => 'text/javascript;charset=utf-8',
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => date("D, d M Y H:i:s T", 0),
    ));
  }

  /**
   * @Route("/searchapi/service", name="js_search_api_service")
   */
  public function serviceAction(Request $request){
    $query = $request->get('query');
    $index = $request->get('index');
    if($query != null && $index != null){
      try{
        $r = IndexManager::getInstance()->search($index, $query);
      }catch(\Exception $ex){
        $r = null;
      }
    }
    return new Response(json_encode($r), 200, array(
      'Content-type' => 'application/json;charset=utf-8',
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => date("D, d M Y H:i:s T", 0),
    ));
  }
}