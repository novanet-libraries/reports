<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json; charset=utf-8');

$validation = array();
$validation[":SUBLIBRARY"] = array_keys(AlephData::sublibraries());
$validation[":COLLECTION"] = array_keys(AlephData::collections());
$validation[":STATUS"]    = array_keys(AlephData::itemStatuses());
foreach($validation as $key => $data){
  if (empty($data)){
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(array('error' => 'Fetching init data failed.')));
  }
}

//sublibrary is required
if (empty($_GET["sublibrary"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply sublibrary.')));
}
if (!in_array($_GET["sublibrary"], $validation[":SUBLIBRARY"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Invalid sublibrary.')));
}

//collection is required
if (empty($_GET["collection"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one collection code.')));
}
if (!in_array($c, $validation[":COLLECTION"][$_GET["sublibrary"]])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Invalid collection code.')));
}

//pstatus is required
if (empty($_GET["status"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one item status.')));
}
foreach($_GET["status"] as $s){
  if (!in_array($s, $validation[":STATUS"])){
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(array('error'=>'Invalid item status.')));
  }
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql = file_get_contents("./query.sql");

    $bind = array();
    foreach($_GET["status"] as $idx => $s){
      $bind[":ST" . $idx] = $s;
    }
    $sql = str_replace(":STATUS", join(",", array_keys($bind)), $sql);

    $bind[":SUBLIBRARY"] = $_GET["sublibrary"];
    $bind[":COLLECTION"] = $_GET["collection"];
    
    $aleph = new AlephOracle(AlephOracle::LIVE);

    $csql = preg_replace('/\bSELECT\b.+?\bFROM\b/is', 'SELECT count(*) FROM', $sql);
    $count = $aleph->querySingle($csql, $bind);
    if ($count >= 50000){
      throw new Exception("This query resulted in more than 50,000 items.  Select fewer items, or contact the office for longer lists.");
    }

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
