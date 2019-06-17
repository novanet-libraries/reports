<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';

header("Content-type: application/json");

function getValidSublibraries(){
  $validCodes = array();
  $prohibited = '/^NOVA|WWW|.+NET|.+BK$/';

  $sourceData = AlephData::sublibraries();
  if (empty($sourceData)){
    throw new Exception("Error fetching initialization data", 500);
  }
  foreach($sourceData as $code => $text){
    if (!preg_match($prohibited, $code)){
      $validCodes[] = $code;
    }
  }
  return $validCodes;
}

try{ //validate input

  if (empty($_GET['sublibrary'])){
    throw new Exception('Must provide sublibrary code');
  }
  if (empty($_GET['start-date']) || empty($_GET['end-date'])){
    throw new Exception('Must provide a date range');
  }
  
  $validSublibs = getValidSublibraries();
  $sublibraries = array();
  foreach($_GET['sublibrary'] as $input){
    $sublibrary = strtoupper($input);
    if (!in_array($sublibrary, $validSublibs)){
      throw new Exception('Invalid sublibrary code');
    }
    $sublibraries[] = $sublibrary;
  }

  //running these through the DateTime constructor to throw if we receive invalid date strings.
  $startDate  = (new DateTime($_GET['start-date']))->format('Ymd');
  $endDate    = (new DateTime($_GET['end-date']))->format('Ymd');
}
catch (Exception $ex){
  if ($ex->getCode() >= 500){
    header("HTTP/1.1 500 Internal Server Error");
  }
  else{
    header("HTTP/1.1 400 Bad Request");
  }
  echo json_encode(array('error' => $ex->getMessage()));
  die();
}

try{ //get report

  $cache = new ReportsCache(basename(__DIR__));
  if ($cache->isStale()){
    
    //make an SQL statement.
    //parameterize/bind the variables we can;
    //find/replace the ones we can't. (usually "... IN ( [list] )..." )
    $inStr = "('" . join("','", $sublibraries) . "')";
    $sql   = str_replace(':SUBLIBRARY', $inStr, file_get_contents('./query.sql'));
    $bind  = array(
      ":STARTDATE" => $startDate,
      ":ENDDATE"   => $endDate
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

