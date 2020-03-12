<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json; charset=utf-8');

$configData = array();
$configData[":SUBLIBRARY"] = array_keys(AlephData::sublibraries());
$configData[":COLLECTION"] = array_keys(AlephData::collections());
$configData[":STATUS"]    = array_keys(AlephData::itemStatuses());
foreach($configData as $key => $data){
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
if (!in_array($_GET["sublibrary"], $configData[":SUBLIBRARY"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Invalid sublibrary.')));
}

//collection is required
if (empty($_GET["collection"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one collection code.')));
}
foreach($_GET["collection"] as $c){
  if (!in_array($c, $configData[":COLLECTION"])){
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(array('error'=>'Invalid collection code.')));
  }
}

//pstatus is required
if (empty($_GET["status"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one item status.')));
}
foreach($_GET["status"] as $s){
  if (!in_array($s, $configData[":STATUS"])){
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(array('error'=>'Invalid item status.')));
  }
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql = file_get_contents("./query.sql");
    $bind = array();
    $bind[":SUBLIBRARY"] = $_GET["sublibrary"];

    $tmpBind = array();
    foreach($_GET["collection"] as $idx => $coll){
      $tmpBind[":COL" . $idx] = $coll;
    }
    $sql = str_replace(":COLLECTIONS", join(",", array_keys($tmpBind)), $sql);
    $bind = array_merge($bind, $tmpBind);

    $tmpBind = array();
    foreach($_GET["status"] as $idx => $s){
      $tmpBind[":ST" . $idx] = $s;
    }
    $sql = str_replace(":STATUS", join(",", array_keys($tmpBind)), $sql);
    $bind = array_merge($bind, $tmpBind);

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
