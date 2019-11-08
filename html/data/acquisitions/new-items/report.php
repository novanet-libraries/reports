<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json');

$startdate   = null;
$sublibrary  = array();

$prohibited  = array('NOVA', 'WWW', 'NSHA');
$validSublibraries = AlephData::sublibraries();
if (empty($validSublibraries)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

//validate input or die()
try{
  //validate start date
  try{
    $now = new DateTime();
    $aYearAgo = (new DateTime())->sub(new DateInterval('P1Y'));
    $startdate = new DateTime($_GET['start-date']);
    if ($startdate > $now || $startdate < $aYearAgo){
      throw new Exception("start date out of range");
    }
    
    $startdate = $startdate->format('Ymd');
  }
  catch (Exception $ex){
    error_log($ex->getMessage());
    throw new Exception('Invalid date');
  }

  //validate sublibrary codes
  $validCodes = array_keys($validSublibraries);
  foreach($_GET['sublibrary'] as $code){
    if (in_array($code, $validCodes) && !in_array($code, $prohibited)){
      $sublibrary[] = $code;
    }
    else{
      throw new Exception('Invalid sublibrary');
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
    foreach($sublibrary as $idx => $code){
      $bind[":LIB$idx"] = $code;
    }
    $sql = str_replace(":SUBLIBRARIES", join(",", array_keys($bind)), $sql);
    $bind[":STARTDATE"] = $startdate;

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

  //this won't happen if $cache->writeJSON() has already started
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => $ex->getMessage()));
}
