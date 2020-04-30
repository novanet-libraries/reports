<?php

class AlephData {

  /* keep a local cache of data from aleph1.novanet.ca */
  private static function getJSON($filename){
    $fullPath = sys_get_temp_dir() . "/AlephData/$filename";

    //should just do this once when you set up the app, rather than check each time we want data.
    if (!is_dir(dirname($fullPath))){
      mkdir(dirname($fullPath), 0755, true);
    }

    $modTime = filemtime($fullPath);
    $minTime = (int) ( (new DateTime())->sub(new DateInterval("PT15H"))->format("U") );
    if (!$modTime || $modTime < $minTime){
      file_put_contents($fullPath, file_get_contents("https://aleph1.novanet.ca/novanet/$filename"));
    }
    return file_get_contents($fullPath);
  }

  public static function itemProcessStatuses(){
    $jsonString = self::getJSON("all-item-process-statuses.json");
    $data = json_decode($jsonString, true);
    return $data;
  }

  public static function itemStatuses(){
    $jsonString = self::getJSON("all-item-statuses.json");
    $data = json_decode($jsonString, true);
    return $data;
  }

  public static function patronStatuses(){
    $jsonString = self::getJSON("all-borrower-status.json");
    $data = json_decode($jsonString, true);
    return $data;
  }

  public static function budgets(){
    $jsonString = self::getJSON("all-budgets.json");
    $data = json_decode($jsonString, true);
    return $data;
  }

  public static function orderUnits(){
    $jsonString = self::getJSON("all-order-units.json");
    $data = json_decode($jsonString, true);
    return $data;
  }

  public static function sublibraries(){
    static $data = null;
    if (!$data){
      $jsonString = self::getJSON("all-sublibraries.json");
      $data = json_decode($jsonString, true);
    }
    return $data;
  }

  public static function collections($flatten = false){
    $jsonString = self::getJSON("all-collection-codes.json");
    $data = json_decode($jsonString, true);

    if ($flatten){
      $out = array();
      foreach($data as $sublib => $collection){
        foreach($collection as $code => $text){
          $out[$code] = $text;
        }
      }
      return $out;
    }

    return $data;
  }
  
  public static function collectionsFlat(){
    return self::collections(true);
  }

  public static function institutionSublibraries(){
    $rv = [];
    foreach(array_keys(self::sublibraries()) as $code){
      $prefix = substr($code,0,2);
      if ($prefix == "DL"){
        $rv["DAL"][] = $code;
      }else if ($prefix == "CC"){
        $rv["NSCC"][] = $code;
      }else if ($prefix == "SF"){
        $rv["StFX"][] = $code;
      }else{
        $rv[$code][] = $code;
      }
    }
    return $rv;
  }
}
