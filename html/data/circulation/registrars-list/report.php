<?php

require "ReportsCache.class.php";
require "AlephOracle.class.php";
require "AlephData.class.php";

header("Content-type: application/json");

$validSublibraries = AlephData::sublibraries();
$validCodes = array();
$prohibited = '/^NSHA|NOVA|WWW|.+NET|.+BK$/';
if (empty($validSublibraries)){
  header("HTTP/1.1 500 Internal Server Error");
  echo json_encode(array("error" => "Fetching init data failed."));
  die();
}
foreach($validSublibraries as $code => $text){
  if (!preg_match($prohibited, $code)){
    $validCodes[] = $code;
  }
}

$sublibrary = strtoupper($_GET["sublibrary"]);
if (empty($sublibrary) || !in_array($sublibrary, $validCodes)){
  header("HTTP/1.1 400 Bad Request");
  $output = array("error" => "Invalid sublibrary code", "valid" => $validCodes);
}
else{
  try{
    $cache = new ReportsCache(basename(__DIR__));

    if ($cache->isStale()){
      $sql     = file_get_contents("./query.sql");
      $bind    = array(":SUBLIBRARY" => $sublibrary);
      $results = array();

      $aleph = new AlephOracle(AlephOracle::LIVE);
      $cache->refresh(
        $aleph->query($sql, $bind),
        $aleph->querySingle('SELECT MAX(last_mviews_refresh) FROM webreport.last_mviews_refresh;')
      );
    }

    $output = $cache->fetch();
  }
  catch (Exception $ex){
    error_log($ex->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    $output = array("error" => $ex->getMessage());
  }
}

echo json_encode($output);
