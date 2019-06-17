<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json');

$startdate   = null;
$sublibrary  = null;
$collections = array();

$prohibited  = '/^(.+RES|NOVA|WWW)$/';
$validSublibraries = AlephData::sublibraries();
$validCollections  = AlephData::collections();
if (empty($validSublibraries) || empty($validCollections)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

//validate input or die()
try{
  //validate start date
  try{
    $now = new DateTime();
    $fiveYearsAgo = (new DateTime())->sub(new DateInterval('P5Y'));
    $startdate = new DateTime($_GET['start-date']);
    if ($startdate > $now || $startdate < $fiveYearsAgo){
      throw new Exception("start date out of range");
    }
    
    $startdate = $startdate->format('Ymd');
  }
  catch (Exception $ex){
    error_log($ex->getMessage());
    throw new Exception('Invalid date');
  }

  //validate sublibrary
  if (in_array($_GET['sublibrary'], array_keys($validSublibraries))){
    $sublibrary = $_GET['sublibrary'];
  }
  else{
    throw new Exception('Invalid sublibrary');
  }

  //validate collection codes
  $validCodes = array_keys($validCollections[$sublibrary]);
  foreach($_GET['collection'] as $code){
    if (in_array($code, $validCodes) && !preg_match($prohibited, $code)){
      $collections[] = $code;
    }
    else{
      throw new Exception('Invalid collection');
    }
  }
}catch(Exception $ex){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(
    array('error' => $ex->getMessage())
  );
  die();
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql = file_get_contents("./query.sql");
    $bind = array();
    foreach($collections as $idx => $code){
      $bind[":COL$idx"] = $code;
    }
    $sql = str_replace(":COLLECTIONS", join(",", array_keys($bind)), $sql);
    $bind[":STARTDATE"] = $startdate;
    $bind[":SUBLIB"] = $sublibrary;

    $aleph = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $aleph->query($sql, $bind),
      $aleph->querySingle('SELECT MAX(last_mviews_refresh) FROM webreport.last_mviews_refresh')
    );
  }

  $cache->writeJSON();

}
catch (Exception $ex){
  error_log($ex->getMessage());

  //this won't happen if $cache->writeJSON() has already started
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => $ex->getMessage()));
}
