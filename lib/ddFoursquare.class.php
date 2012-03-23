<?php


/**
 * ddFoursquare
 *
 * @author david durost <david.durost@gmail.com>
 */
class ddFoursquare {
  protected static 
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
    $debug              = false,
    $token              = null;

  public function setAccessToken($token) { $this->token = $token; }
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
  public static function getFoursquareInstance() {
    return new ddFoursquare();
  }
  public static function retrieve($endpoint, $params = null, $method = 'get') {
    $fs = new self();
    $method = strtolower($method);
    switch($method) {
      case 'get':
        return $fs->get($endpoint, $params);
        break;
      case 'post':
        return $fs->post($endpoint, $params);
        break;
      case 'delete':
        return $fs->delete($endpoint, $params);
        break;
      default:
        throw new ddFoursquareBadRequestException('The given method is invalid.');     
    }
  }

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
    
    if($this->token)
      $params['oauth_token'] = $this->token;
    else  throw new ddFoursquareNotFoundException('no oauth token found.');
    
    if($method === 'GET')
      $url .= is_null($params) ? '' : '?'.http_build_query($params, '', '&');
    
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, "ddFetcher ".time());
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->requestTimeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
//    if(isset($_SERVER ['SERVER_ADDR']) && !empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] != '127.0.0.1')
//      curl_setopt($ch, CURLOPT_INTERFACE, $_SERVER ['SERVER_ADDR']);
    
    if($method === 'POST' && $params !== null)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    
    $data = curl_exec($ch);
    $meta = json_decode($data,true);
    if($meta['meta']["code"] != 200)
      throw new ddFoursquareException('error encountered getting data.');
      
    return $data;
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
class ddCurl
{
  const timeout = 3;
  static $inst = null;
  static $singleton = 0;
  private $mc;
  private $msgs;
  private $running;
  private $execStatus;
  private $selectStatus;
  private $sleepIncrement = 1.1;
  private $requests = array();
  private $responses = array();
  private $properties = array();
  private static $timers = array();

  function __construct()
  {
    if(self::$singleton == 0)
    {
      throw new Exception('This class cannot be instantiated by the new keyword.  You must instantiate it using: $obj = ddCurl::getInstance();');
    }

    $this->mc = curl_multi_init();
    $this->properties = array(
      'code'  => CURLINFO_HTTP_CODE,
      'time'  => CURLINFO_TOTAL_TIME,
      'length'=> CURLINFO_CONTENT_LENGTH_DOWNLOAD,
      'type'  => CURLINFO_CONTENT_TYPE,
      'url'   => CURLINFO_EFFECTIVE_URL
      );
  }

  public function addCurl($ch)
  {
    $key = $this->getKey($ch);
    $this->requests[$key] = $ch;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));

    $code = curl_multi_add_handle($this->mc, $ch);
    $this->startTimer($key);
    
    // (1)
    if($code === CURLM_OK || $code === CURLM_CALL_MULTI_PERFORM)
    {
      do {
          $code = $this->execStatus = curl_multi_exec($this->mc, $this->running);
      } while ($this->execStatus === CURLM_CALL_MULTI_PERFORM);

      return new ddCurlManager($key);
    }
    else
    {
      return $code;
    }
  }

  public function getResult($key = null)
  {
    if($key != null)
    {
      if(isset($this->responses[$key]))
      {
        return $this->responses[$key];
      }

      $innerSleepInt = $outerSleepInt = 1;
      while($this->running && ($this->execStatus == CURLM_OK || $this->execStatus == CURLM_CALL_MULTI_PERFORM))
      {
        usleep($outerSleepInt);
        $outerSleepInt = intval(max(1, ($outerSleepInt*$this->sleepIncrement)));
        $ms=curl_multi_select($this->mc, 0);
        if($ms > 0)
        {
          do{
            $this->execStatus = curl_multi_exec($this->mc, $this->running);
            usleep($innerSleepInt);
            $innerSleepInt = intval(max(1, ($innerSleepInt*$this->sleepIncrement)));
          }while($this->execStatus==CURLM_CALL_MULTI_PERFORM);
          $innerSleepInt = 1;
        }
        $this->storeResponses();
        if(isset($this->responses[$key]['data']))
        {
          return $this->responses[$key];
        }
        $runningCurrent = $this->running;
      }
      return null;
    }
    return false;
  }

  public static function getSequence()
  {
    return new ddSequence(self::$timers);
  }

  public static function getTimers()
  {
    return self::$timers;
  }

  private function getKey($ch)
  {
    return (string)$ch;
  }

  private function headerCallback($ch, $header)
  {
    $_header = trim($header);
    $colonPos= strpos($_header, ':');
    if($colonPos > 0)
    {
      $key = substr($_header, 0, $colonPos);
      $val = preg_replace('/^\W+/','',substr($_header, $colonPos));
      $this->responses[$this->getKey($ch)]['headers'][$key] = $val;
    }
    return strlen($header);
  }

  private function storeResponses()
  {
    while($done = curl_multi_info_read($this->mc))
    {
      $key = (string)$done['handle'];
      $this->stopTimer($key, $done);
      $this->responses[$key]['data'] = curl_multi_getcontent($done['handle']);
      foreach($this->properties as $name => $const)
      {
        $this->responses[$key][$name] = curl_getinfo($done['handle'], $const);
      }
      curl_multi_remove_handle($this->mc, $done['handle']);
      curl_close($done['handle']);
    }
  }

  private function startTimer($key)
  {
    self::$timers[$key]['start'] = microtime(true);
  }

  private function stopTimer($key, $done)
  {
      self::$timers[$key]['end'] = microtime(true);
      self::$timers[$key]['api'] = curl_getinfo($done['handle'], CURLINFO_EFFECTIVE_URL);
      self::$timers[$key]['time'] = curl_getinfo($done['handle'], CURLINFO_TOTAL_TIME);
      self::$timers[$key]['code'] = curl_getinfo($done['handle'], CURLINFO_HTTP_CODE);
  }

  static function getInstance()
  {
    if(self::$inst == null)
    {
      self::$singleton = 1;
      self::$inst = new ddCurl();
    }

    return self::$inst;
  }
}

class ddCurlManager
{
  private $key;
  private $ddCurl;

  public function __construct($key)
  {
    $this->key = $key;
    $this->ddCurl = ddCurl::getInstance();
  }

  public function __get($name)
  {
    $responses = $this->ddCurl->getResult($this->key);
    return isset($responses[$name]) ? $responses[$name] : null;
  }

  public function __isset($name)
  {
    $val = self::__get($name);
    return empty($val);
  }
}

/*
 * Credits:
 *  - (1) Alistair pointed out that curl_multi_add_handle can return CURLM_CALL_MULTI_PERFORM on success.
 */