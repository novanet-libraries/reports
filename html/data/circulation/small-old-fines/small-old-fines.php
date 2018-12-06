<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';

header("Content-type: application/json");

if (empty($_GET['sublibrary'])){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(array('error' => 'Must provide sublibrary code'));
  die();
}

$sublibraries = array();
$validCodes = array_keys(AlephData::sublibraries());
foreach($_GET['sublibrary'] as $input){
  $sublibrary = trim(strtoupper($input));
  if (!in_array($sublibrary, $validCodes)){
    header('HTTP/1.1 400 Bad Request');
    echo json_encode($output = array('error' => 'Invalid sublibrary code'));
    die();
  }
  $sublibraries[] = $sublibrary;
}

try{
  $cache = new ReportsCache('small-old-fines');

  if ($cache->isStale()){
    $sql = file_get_contents("./small-old-fines.sql");

    //replace IN ( :SUBLIBRARIES ) with in IN (:SUB0, :SUB1, :SUB2, etc.)
    $bind = array();
    foreach($sublibraries as $idx => $code){
      $bind[":SUB$idx"] = $code;
    }
    $sql = str_replace(":SUBLIBRARIES", join(", ", array_keys($bind)), $sql);

    //set :CUTOFFDATE to some time 5 years ago.
    $bind[":CUTOFFDATE"] = (new DateTime((date('Y')-5).'-06-01'))->format('Ymd');

    $db = new AlephOracle(AlephOracle::LIVE);
    $results = array();
    foreach($db->query($sql, $bind) as $row){
      $results[] = $row;
    }
    $cache->refresh($results);

    $output = array(
      'date' => date('Y-m-d H:i:s'),
      'data' => $results
    );
  }
  else{
    $output = $cache->fetch();
  }
}
catch (Exception $ex){
  error_log($ex->getMessage());
  header('HTTP/1.1 500 Internal Server Error');
  $output = array('error' => $ex->getMessage());
}

echo json_encode($output);
