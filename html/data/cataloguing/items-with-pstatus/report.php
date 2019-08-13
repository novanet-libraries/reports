<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json; charset=utf-8');

$configData = array();
$configData[":SUBLIBRARY"] = array_keys(AlephData::sublibraries());
$configData[":PSTATUS"]    = array_keys(AlephData::itemProcessStatuses());
foreach($configData as $key => $data){
  if (empty($data)){
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(array('error' => 'Fetching init data failed.')));
  }
}

//sublibrary is required
if (empty($_GET["sublibrary"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one sublibrary.')));
}
foreach($_GET["sublibrary"] as $lib){
  if (!in_array($lib, $configData[":SUBLIBRARY"])){
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(array('error'=>'Invalid sublibrary.')));
  }
}

//pstatus is required
if (empty($_GET["process-status"])){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error'=>'Must supply at least one process status.')));
}
foreach($_GET["process-status"] as $p){
  if (!in_array($p, $configData[":PSTATUS"])){
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(array('error'=>'Invalid process status.')));
  }
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql = file_get_contents("./query.sql");
    $bind = array();

    $tmpBind = array();
    foreach($_GET["sublibrary"] as $idx => $lib){
      $tmpBind[":SUB" . $idx] = $lib;
    }
    $sql = str_replace(":SUBLIBRARIES", join(",", array_keys($tmpBind)), $sql);
    $bind = array_merge($bind, $tmpBind);

    $tmpBind = array();
    foreach($_GET["process-status"] as $idx => $p){
      $tmpBind[":PST" . $idx] = $p;
    }
    $sql = str_replace(":PSTATUS", join(",", array_keys($tmpBind)), $sql);
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
