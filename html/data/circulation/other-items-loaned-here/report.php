<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');

header('Content-type: application/json');

$startdate   = null;
$sublibrary  = null;

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
    $fiveYearsAgo = (new DateTime())->sub(new DateInterval('P5Y'));
    $startdate = new DateTime($_GET['start-date']);
    if ($startdate > $now || $startdate < $fiveYearsAgo){
      throw new Exception("start date out of range");
    }

    //for the particular table we are querying, date is a 15 character string that starts with YYYYMMDD
    $startdate = $startdate->format('Ymd') . '0000000';
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
    $bind[":STARTDATE"]  = $startdate;
    $bind[":SUBLIBRARY"] = $sublibrary;
    $bind[":CIRCDESK"]   = $sublibrary."CDESK";

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
