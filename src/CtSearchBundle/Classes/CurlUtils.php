<?php
/**
 * Created by PhpStorm.
 * User: Louis Sicard
 * Date: 09/05/2016
 * Time: 17:07
 */

namespace CtSearchBundle\Classes;


class CurlUtils
{

  public static function handleCurlProxy(&$ch){
    global $kernel;
    if ($kernel->getContainer()->getParameter('ct_search.use_proxy')) {
      curl_setopt($ch, CURLOPT_PROXY, $kernel->getContainer()->getParameter('ct_search.proxy_host'));
      if ($kernel->getContainer()->getParameter('ct_search.proxy_type') == 'http')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
      elseif ($kernel->getContainer()->getParameter('ct_search.proxy_type') == 'socks5')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
      elseif ($kernel->getContainer()->getParameter('ct_search.proxy_type') == 'socks4')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
    }
  }

}