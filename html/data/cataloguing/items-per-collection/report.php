<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';

header("Content-type: application/json");

if (empty($_GET["sublibrary"])){
  header("HTTP/1.1 400 Bad Request");
  die(json_encode(array("error" => "Must supply sublibrary code")));
}

$validCodes = array_keys(AlephData::sublibraries());
$sublibraries = array_values((array) $_GET["sublibrary"]);
foreach($sublibraries as $code){
  if (!in_array($code, $validCodes)){
    header("HTTP/1.1 400 Bad Request");
    die(json_encode(array("error" => "Invaid sublibrary code")));
  }
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){

    foreach($sublibraries as $idx => $code){
      $bind[":SUB$idx"] = $code;
    }
    $sql = str_replace(
      ":SUBLIBRARY",
      join(",", array_keys($bind)),
      "SELECT COLLECTION, COUNT(*) AS C
         FROM WEBREPORT.ITEM_RECORDS
        WHERE SUB_LIBRARY = :SUBLIBRARY 
        GROUP BY COLLECTION"
    );
    $aleph = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $aleph->query($sql, $bind),
      $aleph->querySingle("SELECT TO_CHAR(MAX(last_mviews_refresh), 'YYYY-MM-DD HH24:MI:SS') FROM webreport.last_mviews_refresh")
    );
  }

  $output = $cache->fetch();
}
catch (Exception $ex){
  error_log($ex->getMessage());
  header('HTTP/1.1 500 Internal Server Error');
  $output = array('error' => $ex->getMessage());
}

echo json_encode($output);
