<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json; charset=utf-8');

$configData = array();

$configData[":SUBLIBRARIES"] = AlephData::sublibraries();
$configData[":COLLECTIONS"]  = AlephData::collections();
$configData[":MATERIALS"]    = AlephData::materialTypes();
$configData[":ISTATUSES"]    = AlephData::itemStatuses();
$configData[":PSTATUSES"]    = AlephData::iteProcessStatuses();

foreach($confgData as $key => $data){
  if (empty($data)){
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(array('error' => 'Fetching init data failed.')));
  }
}
try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql  = file_get_contents("./query.sql");
    $bind = array();

    //alter the SQL and build the $bind array:
    foreach($configData as $key => $data){
      $tmpBind = array();
      foreach($configData[$key] as $idx => $code){
        $tmpBind[substr($key,0,4).$idx] = $code;
      }
      $sql = str_replace($key, join(",", array_keys($tmpBind)), $sql);
      array_push($bind, ...$tmpBind);
    }    
    
    $aleph = new AlephOracle(AlephOracle::LIVE);    
    $cache->refresh(
      $aleph->query($sql, $bind),
      $aleph->querySingle("SELECT TO_CHAR(MAX(last_mviews_refresh), 'YYYY-MM-DD HH24:MI:SS') FROM webreport.last_mviews_refresh")
    );
  }

  $cache->writeJSON();

}
catch (Exception $ex){
  error_log($ex->getMessage());
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => $ex->getMessage()));
}
