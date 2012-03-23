<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of ddFoursquareBase
 *
 * @author ner0tic
 */
class ddFoursquareBase implements ArrayAccess, Countable, IteratorAggregate {
  protected 
    $_array     = array();
  public 
    $id         = null,
    $createdAt  = null;
  
    
  public function getId() { return $this->id; }
  public function setId($id) { 
    if(!is_null($id)) $this->id = $id; 
    else  throw new ddFoursquareNotFoundException('given id is null.');
  }
  public function __construct($id,$createdAt = null) {
   $this->setId($id); 
   $this->createdAt($createdAt);
  }
  public function createdAt($date = null) { 
    if(is_null($date))  return $this->createdAt; 
    else  $this->createdAt = $date;    
  }
  
  public function fromArray($a) {
    if(!is_array($a)) throw new ddFoursquareException('given variable was not an array.');
    if(!isset($a['id']))  throw new ddFoursquareNotFoundException('id not found in given array.');
    foreach($a as $k => $v) {
      if(is_array($v)) {
        foreach($v as $kk => $vv) $this->$k[$kk] = $vv;
      }
      else  $this->$k = $v;
    }
  }
  
  public function count() { return count($this->_array); }
  public function getIterator() { return new ArrayObject($this->_array); }
  public function offsetExists($n) { return isset($this->_array[$n]); }
  public function offsetGet($n) { return $this->_array[$n]; }
  public function offsetSet($n,$v) { return $this->_array[$n] = $v; }
  public function offsetUnset($n) { unset($this->_array[$n]); }
}

?>
