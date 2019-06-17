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
      "SELECT Z30_COLLECTION, COUNT(*) AS C
         FROM NOV50.Z30
        WHERE RTRIM(Z30_SUB_LIBRARY) IN ( :SUBLIBRARY )
        GROUP BY Z30_COLLECTION"
    );
    $aleph = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $aleph->query($sql, $bind)
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
