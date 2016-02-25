<?php

namespace CtSearchBundle\Controller;

use CtSearchBundle\CtSearchBundle;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use CtSearchBundle\Classes\IndexManager;
use Symfony\Component\HttpFoundation\Response;

class RecommandationController extends Controller {

  /**
   * @Route("/reco/reco.js", name="reco-js")
   */
  public function getRecoJSAction(Request $request) {

    if(!isset($_COOKIE['reco_sig'])){
      $reco_sig = $this->generateGuid();
      setcookie('reco_sig', $reco_sig, time() + 700 * 24 * 60 * 60);
    }
    else{
      $reco_sig = $_COOKIE['reco_sig'];

    }

    $env = $this->container->get( 'kernel' )->getEnvironment();

    $js = '(function(){

  var getXMLHTTPRequest = function() {
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

  if(typeof(regReco_id) !== "undefined"){

    var url = "//' . $_SERVER['HTTP_HOST'] . ($env != 'prod' ? '/app_' . $env . '.php' : '') . '/reco/report";
    var url_get = "//' . $_SERVER['HTTP_HOST'] . ($env != 'prod' ? '/app_' . $env . '.php' : '') . '/reco/get";
    var reco_sig = "' . $reco_sig . '";

    var xhr = getXMLHTTPRequest();
    xhr.open("POST", url, true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("reco_sig=" + encodeURIComponent(reco_sig) + "&regReco_id=" + encodeURIComponent(regReco_id));

    if(typeof(regReco_callback) !== "undefined" && typeof(regReco_target) !== "undefined"){
      var xhr2 = getXMLHTTPRequest();
      xhr2.onreadystatechange = function(){
        if(xhr2.readyState == 4 && xhr2.status == 200){
          eval("var ctsearch_reco_xhr2_resp = " + xhr2.responseText);
          window[regReco_callback](ctsearch_reco_xhr2_resp);
        }
      };
      xhr2.open("POST", url_get, true);
      xhr2.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      xhr2.send("regReco_id=" + encodeURIComponent(regReco_id) + "&regReco_target=" + encodeURIComponent(regReco_target));
    }
  }
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
   * @Route("/reco/report", name="reco-report")
   */
  public function getRecoReport(Request $request){

    $host = parse_url($_SERVER['HTTP_REFERER'])['host'];
    $path_id = $request->get('reco_sig') . '_' . str_replace('.', '_', $host);

    $path = IndexManager::getInstance()->getRecoPath($path_id, $host);
    if($path == null) {
      $path = array(
        'id' => $path_id,
        'host' => $host,
        'ids' => array($request->get('regReco_id')),
      );
      IndexManager::getInstance()->saveRecoPath($path);
    }
    else{
      if(!is_array($path['ids']) && !empty($path['ids'])){
        $path['ids'] = array($path['ids']);
      }
      if(!in_array($request->get('regReco_id'), $path['ids'])){
        $path['ids'][] = $request->get('regReco_id');
        IndexManager::getInstance()->saveRecoPath($path);
      }
    }

    return new Response(json_encode($path, JSON_PRETTY_PRINT), 200, array(
      'Content-type' => 'text/plain;charset=utf-8',
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => date("D, d M Y H:i:s T", 0),
    ));
  }

  /**
   * @Route("/reco/get", name="reco-get")
   */
  public function getRecos(Request $request)
  {

    if (isset($_SERVER['HTTP_REFERER']))
      $host = parse_url($_SERVER['HTTP_REFERER'])['host'];
    else
      $host = $request->get('host');
    $id = $request->get('regReco_id');
    $target = $request->get('regReco_target');
    if (count(explode('.', $target)) == 2) {
      $index = explode('.', $target)[0];
      $mapping = explode('.', $target)[1];

      $recos = IndexManager::getInstance()->getRecos($id, $host, $index, $mapping);
    }
    else{
      $recos = array();
    }

    $r = array();
    foreach($recos as $id_reco => $reco){
      $r[] = array('_id' => $id_reco) + $reco;
    }

    return new Response(json_encode($r), 200, array(
      'Content-type' => 'text/plain;charset=utf-8',
      'Access-Control-Allow-Origin' => '*',
      'Cache-Control' => 'no-cache, no-store, must-revalidate',
      'Pragma' => 'no-cache',
      'Expires' => date("D, d M Y H:i:s T", 0),
    ));
  }

  private function generateGuid(){
    mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
    $charid = strtoupper(md5(uniqid(rand(), true)));
    $hyphen = chr(45);// "-"
    $uuid = substr($charid, 0, 8)
      .substr($charid, 8, 4)
      .substr($charid,12, 4)
      .substr($charid,16, 4)
      .substr($charid,20,4);// "}"
    return $uuid;
  }

}
