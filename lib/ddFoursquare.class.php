9*<?php

/**
 * ddFoursquare
 *
 * @author david durost <david.durost@gmail.com>
 */
class ddFoursquare extends fourSquare {
  protected 
    $_foursquare        = false;
  protected 
    $requestTokenUrl    = 'https://foursquare.com/oauth2/authenticate',
    $accessTokenUrl     = 'https://foursquare.com/oauth2/access_token',
    $authorizeUrl       = 'https://foursquare.com/oauth2/authorize',
    $apiUrl             = 'https://api.foursquare.com';
  protected 
    $apiVersion         = 'v2',
    $isAsynchronous     = false,
    $followLocation     = false,
    $connectionTimeout  = 5,
    $requestTimeout     = 30,
    $debug              = false;

  public function setAccessToken($accessToken) { $this->accessToken = $accessToken; }
  public function setTimeout($requestTimeout = null, $connectionTimeout = null) {
    if($requestTimeout !== null)
      $this->requestTimeout = floatval($requestTimeout);
    if($connectionTimeout !== null)
      $this->connectionTimeout = floatval($connectionTimeout);
  }
  public function useApiVersion($version = null) { $this->apiVersion = $version; }
  public function useAsynchronous($async = true) { $this->isAsynchronous = (bool)$async; }
  
  public function delete($endpoint, $params = null) { return $this->request('DELETE', $endpoint, $params); }
  public function get($endpoint, $params = null) { return $this->request('GET', $endpoint, $params); }
  public function post($endpoint, $params = null) { return $this->request('POST', $endpoint, $params); }

  public function __construct() { $this->setAccessToken(sfConfig::get('app_foursquare_api_key')); }

  private function getApiUrl($endpoint) {
    if(!empty($this->apiVersion))
      return "$this->apiUrl/$this->apiVersion/$endpoint";
    else
      return "$this->apiUrl/$endpoint";
  }

  private function request($method, $endpoint, $params = null) {
    if(preg_match('#^https?://#', $endpoint))
      $url = $endpoint;
    else
      $url = $this->getApiUrl($endpoint);
    
    if($this->accessToken)
      $params['oauth_token'] = $this->accessToken;
    else  throw new ddFoursquareNotFoundException('no oauth token found.');
    
    if($method === 'GET')
      $url .= is_null($params) ? '' : '?'.http_build_query($params, '', '&');
    
    $ch  = curl_init($url);
    curl_setopt($ch, CURLOPT_USERAGENT, "ddFetcher ".time());
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    if(isset($_SERVER ['SERVER_ADDR']) && !empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1')
      curl_setopt($ch, CURLOPT_INTERFACE, $_SERVER ['SERVER_ADDR']);
    
    if($method === 'POST' && $params !== null)
      url_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    
    $r = new ddFoursquareJson(ddCurl::getInstance()->addCurl($ch), $this->debug);
    
    if(!$this->isAsynchronous)
      $r->responseText;
    
    return $r;
  }
}

class ddFoursquareJson implements ArrayAccess, Countable, IteratorAggregate {
  private $debug;
  private $_response;

  public function __construct($response, $debug = false) {
    $this->_response = $response;
    $this->debug  = $debug;
  }
  public function __destruct() { $this->responseText; }
  public function getIterator () {
    if ($this->__obj)
      return new ArrayIterator($this->__obj);
    else
      return new ArrayIterator($this->response);   
  }
  public function count () { return count($this->response); }
  public function offsetSet($offset, $value)  { $this->response[$offset] = $value; }
  public function offsetExists($offset)  { return isset($this->response[$offset]); }
  public function offsetUnset($offset)  { unset($this->response[$offset]); }
  public function offsetGet($offset)  { return isset($this->response[$offset]) ? $this->response[$offset] : null; }
  public function __get($name) {
    $accessible = array('responseText'=>1,'headers'=>1,'code'=>1);
    $this->responseText = $this->_response->data;
    $this->headers      = $this->_response->headers;
    $this->code         = $this->_response->code;
    if(isset($accessible[$name]) && $accessible[$name])
      return $this->$name;
    elseif(($this->code < 200 || $this->code >= 400) && !isset($accessible[$name]))
      ddFoursquareException::raise($this->_response, $this->debug);
    $this->response     = json_decode($this->responseText, 1);
    $this->__obj        = json_decode($this->responseText);

    if(gettype($this->__obj) === 'object') {
      foreach($this->__obj as $k => $v) $this->$k = $v;
    }
    if (property_exists($this, $name))
      return $this->$name;
    return null;
  }
  public function __isset($name) {
    $value = self::__get($name);
    return !empty($name);
  }
}
class ddFoursquareException extends Exception {
  public static function raise($response, $debug) {
    $message = $response->data;
    switch($response->code) {
      case 400:
        throw new ddFoursquareBadRequestException($message, $response->code);
      case 401:
        throw new ddFoursquareNotAuthorizedException($message, $response->code);
      case 403:
        throw new ddFoursquareForbiddenException($message, $response->code);
      case 404:
        throw new ddFoursquareNotFoundException($message, $response->code);
      default:
        throw new ddFoursquareException($message, $response->code);
    }
  }
}
class ddFoursquareBadRequestException extends ddFoursquareException{}
class ddFoursquareNotAuthorizedException extends ddFoursquareException{}
class ddFoursquareForbiddenException extends ddFoursquareException{}
class ddFoursquareNotFoundException extends ddFoursquareException{}