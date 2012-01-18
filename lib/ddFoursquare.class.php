<?php

/**
 * ddFoursquare
 *
 * @author david durost <david.durost@gmail.com>
 */
class ddFoursquare extends fourSquare {
  protected
    $_foursquare = false;
  
  public function __construct() {
    parent::construct(sfConfig::get('app_foursquare_api_key'));
  }
}

?>
