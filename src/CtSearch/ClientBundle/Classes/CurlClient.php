<?php
/**
 * Created by PhpStorm.
 * User: louis
 * Date: 21/09/2016
 * Time: 15:59
 */

namespace CtSearch\ClientBundle\Classes;


class CurlClient
{

  private $url;
  private $username = NULL;
  private $password = NULL;
  private $proxyType = NULL;
  private $proxyHost = NULL;

  /**
   * CurlClient constructor.
   * @param string $url
   */
  public function __construct($url)
  {
    $this->url = $url;
  }

  public function setBasicAuthCredentials($username, $password){
    $this->username = $username;
    $this->password = $password;
  }

  /**
   * @return array
   */
  public function getResponse(){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    if($this->username != NULL && $this->password != NULL){
      curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->password);
    }

    if($this->proxyType != NULL && $this->proxyHost != NULL){
      curl_setopt($ch, CURLOPT_PROXY, $this->proxyHost);
      if ($this->proxyType == 'http')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
      elseif ($this->proxyType == 'socks5')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
      elseif ($this->proxyType == 'socks4')
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
    }

    $r = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($r, 0, $header_size);
    $headers_r = explode(PHP_EOL, $header);
    $headers = [];
    foreach($headers_r as $hh){
      if(strpos($hh, ":") !== FALSE){
        $headers[trim(substr($hh, 0, strpos($hh, ':')))] = trim(substr($hh, strpos($hh, ':') + 1));
      }
    }
    $body = substr($r, $header_size);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return array(
      'code' => $code,
      'data' => $body,
      'headers' => $headers
    );
  }

  /**
   * proxyType can be "http", "socks4" or "socks5"
   */
  public function setProxyInformation($proxyType, $proxyHost){
    $this->proxyType = $proxyType;
    $this->proxyHost = $proxyHost;
  }

}