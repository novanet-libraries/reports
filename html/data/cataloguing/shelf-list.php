<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json; charset=utf-8');

//validate input
$sublibrary  = null;
$collections = array();
$inputErrors = array();
$prohibited  = '/^(AUOLB|AUOLS|.+NET|.+RES|NOVA|WWW)$/';
$validSublibraries = AlephData::sublibraries();
$validCollections  = AlephData::collections();
if (empty($validSublibraries) || empty($validCollections)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

$validCodes = array_keys($validSublibraries);
$input = strtoupper($_GET['sublibrary']);
if (in_array($input, $validCodes)){
  $sublibrary = $input;
}
else{
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(
    array(
      'error' => 'Invalid sublibrary',
      'valid' => $validCodes
    )
  );
  die();
}

$validCodes = array_keys($validCollections[$sublibrary]);
$input = $_GET['collection'];
foreach($input as $code){
  $code = strtoupper($code);
  if (!in_array($code, $validCodes)){
    $inputErrors[] = "collection code does not exist in '$sublibrary'";
  }
  else if (preg_match($prohibited, $code)){
    $inputErrors[] = "collection code is prohibited from this report";
  }
  else{
    $collections[] = $code;
  }
}

if (count($collections) > 5){
  $inputErrors[] = "too many collection codes";
  $collections = array();
}

if (empty($collections)){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(
    array(
      'error'      => 'Invalid collection',
      'errors'     => $inputErrors,
      'valid'      => $validCodes,
      'prohibited' => $prohibited
    )
  );
  die();
}

try{
  $cache = new ReportsCache('shelf-list');

  if ($cache->isStale()){
    $sql = file_get_contents("./shelf-list.sql");

    //replace IN ( :COLLECTION ) with in IN (:COL0, :COL1, :COL2, etc.)
    $bind = array();
    foreach($collections as $idx => $code){
      $bind[":COL$idx"] = $code;
    }
    $sql = str_replace(":COLLECTIONS", join(",", array_keys($bind)), $sql);

    $bind[":SUBLIB"] = $sublibrary;

    $db = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $db->query($sql, $bind)
    );
//    $results = array();
//    foreach($db->query($sql, $bind) as $row){
//      $results[] = $row;
//    }
//    $cache->refresh($results);
//
//    $output = array(
//      'date' => date('Y-m-d H:i:s'),
//      'data' => $results
//    );
  }

  $cache->writeJSON();

//  else{
//    $output = $cache->fetch();
//  }
}
catch (Exception $ex){
  error_log($ex->getMessage());
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => $ex->getMessage()));
}
