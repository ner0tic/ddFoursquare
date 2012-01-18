<?php

/*
 * Foursquare-php Foursquare checkins fetcher php class
 * Connect to your foursquare account and retrive your last chekin location to display it along with a google map on your page
 * Author: Elie Bursztein http://elie.im / @elie on Twitter
 * INSTALL: For installation instructions and how to get an oauth token see 
 * URL: http://code.google.com/p/foursquare-php/
 * Version: 0.2
 * License: GPL v3
 */

class fourSquare {

    private $token = "";
    private $rawData = "";  ///\! FourSquare raw data as a php object
    //private $url = "http://api.foursquare.com/v1/user.json"; //foursquare api call
    private $url = "https://api.foursquare.com/v2/users/self/checkins?oauth_token=";
    public $date = "";
    public $venueName = "";
    public $venueCat = "";
    public $venueType = "";
    public $venueIcon = "http://foursquare.com/img/categories/question.png";
    public $comment = "";
    public $venueAddress = "";
    public $venueCity = "";
    public $venueState = "";
    public $venueCountry = "";
    public $geolong = "";
    public $geolat = "";


    /*
     * Parse Foursquare data to extract the data of a given checking
     *
     * Works with the foursquare v2 API
     * @param $number the checking number in reverse order (0 last checking, 1 previous checking, 2 (prev prev checking)...
     * 
     * @version 0.2
     * @date 08/24/11 
     * @author Elie
     */

    function getCheckin($position = 0) {


        //parsing
        try {
            //$this->date
            $root = $this->rawData->{"response"}->{"checkins"}->{"items"}{$position};
            //print_r($this->rawData);
            //print_r($root);

            $timestamp = $root->{"createdAt"};
            $timezone = $root->{"timeZone"};
            date_default_timezone_set($timezone);
            $this->date = date("F j, Y, g:i a", $timestamp);

            $this->venueName = $root->{"venue"}->{"name"};
            //print_r($root->{"venue"}->{"categories"}[0]);
            if (isset($root->{"venue"}->{"categories"}[0])) {
                $this->venueCat = $root->{"venue"}->{"categories"}[0]->{"name"};
                $this->venueIcon = $root->{"venue"}->{"categories"}[0]->{"icon"};
                if (isset($root->{"venue"}->{"categories"}[0]->{"parents"}[0]))
                    $this->venueType = $root->{"venue"}->{"categories"}[0]->{"parents"}[0];
            }

            if (isset($root->{"shout"})) {
                $this->comment = $root->{"shout"};
            }

            if (isset($root->{"venue"}->{"location"})) {
                if (isset($root->{"venue"}->{"location"}->{"address"}))
                    $this->venueAddress = $root->{"venue"}->{"location"}->{"address"};
                if (isset($root->{"venue"}->{"location"}->{"city"}))
                    $this->venueCity = $root->{"venue"}->{"location"}->{"city"};
                if (isset($root->{"venue"}->{"location"}->{"state"}))
                    $this->venueState = $root->{"venue"}->{"location"}->{"state"};
                if (isset($root->{"venue"}->{"location"}->{"country"}))
                    $this->venueCountry = $root->{"venue"}->{"location"}->{"country"};
                $this->geolat = $root->{"venue"}->{"location"}->{"lat"};
                $this->geolong = $root->{"venue"}->{"location"}->{"lng"};
            }
        } catch (Exception $e) {
            
        }
    }

    /*
     * Get the foursquare location and convert the fist checkin as fq object
     *
     * Works with the foursquare v2 API.
     * @param $token foursquare oauth token v2
     * @param $safe doing SSL certificate validation ? Does not seems to work on most computer so turned off by default (SIC :()
     * 
     * @version 0.2
     * @date 08/24/11 
     * @author Elie
     */

    function __construct($token, $safe = false) {

        $req = $this->url . $token;
        //fetching data
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $req);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERAGENT, "fetcher " . time()); //prevent rate limit
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $safe);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $data = curl_exec($ch);
        curl_close($ch);

        //echo "$data";
        //decoding data
        $this->rawData = json_decode($data);

        if ($this->rawData->{"meta"}->{"code"} != 200) {
            die("Foursquare widget API: Error getting checking. Is the App still authorized ?");
        }

        #parse the last checking data ($number == 0)
        $this->getCheckin(0);
    }

    /*
     * Get the google map img url corresponding to the foursquare location
     * 
     * @param $width map width
     * @param $height map height
     * @param $mobile is this a map for a mobile page ? default false
     * @param $maptype what type of map to render. Possible choice : roadmap, satellite, hybrid, and terrain. Default "roadmap"
     * @param $zoom : level of zoom in the map default 12
     * @param $markerText: text en the marker default "" (can only be a single uppercase letter) put "" for a .
     * @param $markerText: color of the marker default blue
     * 
     * @return the url of the map to include in an SRC
     * 
     * @version 0.2
     * @date 08/24/11 
     * @author Elie
     * 
     */

    public function getMapUrl($width, $height, $zoom = 12, $markerText = "me", $markerColor = "blue", $mobile = FALSE, $maptype = "roadmap") {



        $mapUrl = "http://maps.google.com/maps/api/staticmap?";      //static map url
        $mapUrl .= "sensor=true";                                     //we are using the GPS through foursquare so it is a sensor map.
        $mapUrl .= "&center=" . $this->geolat . "," . $this->geolong;   //position returned by foursquare

        $mapUrl .= "&maptype=" . $maptype;

        $mapUrl .= "&size=" . $width . "x" . $height;
        $mapUrl .= "&zoom=" . $zoom;

        $markerText = strtoupper(substr($markerText, 0, 1));
        $mapUrl .= "&markers=color:" . $markerColor . "|label:" . $markerText . "|" . $this->geolat . "," . $this->geolong . "|";


        return $mapUrl;
    }

    /*
     * Return the location encoded in the JSON format. 
     * 
     * This function is mainly intended to create AJAX interface and call the lib via XHR request
     * 
     * @param $position which checking position to get in inverse order. Default is 0 the latest one.
     * @return $json the checkin information encode in json. 
     * @version 0.1
     * @date 08/24/11 
     * @author Elie
     */

    public function getJson($position = 0) {

        /*
         *    public $date = "";
          public $venueName = "";
          public $venueCat = "";
          public $venueType = "";
          public $venueIcon = "http://foursquare.com/img/categories/question.png";
          public $comment = "";
          public $venueAddress = "";
          public $venueCity = "";
          public $venueState = "";
          public $venueCountry = "";
          public $geolong = "";
          public $geolat = "";
         */


        $data = Array();
        $data["venueName"] = $this->venueName;
        $data["venueCat"] = $this->venueCat;
        $data["venueType"] = $this->venueType;
        $data["venuIcon"] = $this->venueIcon;
        $data["comment"] = $this->comment;
        $data["venueAddress"] = $this->venueAddress;
        $data["venueCity"] = $this->venueCity;
        $data["venueState"] = $this->venueState;
        $data["venueCountry"] = $this->venueCountry;
        $data["geolong"] = $this->geolong;
        $data["geolat"] = $this->geolat;

        $json = json_encode($data);
        return $json;
    }

}

?>