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
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql  = file_get_contents("./query.sql");
    $csql = preg_replace('/\bSELECT\b.+?\bFROM\b/is', 'SELECT count(*) FROM', $sql);

    //replace IN ( :COLLECTION ) with in IN (:COL0, :COL1, :COL2, etc.)
    $bind = array();
    foreach($collections as $idx => $code){
      $bind[":COL$idx"] = $code;
    }
    $sql  = str_replace(":COLLECTIONS", join(",", array_keys($bind)), $sql);
    $csql = str_replace(":COLLECTIONS", join(",", array_keys($bind)), $csql);

    $bind[":SUBLIB"] = $sublibrary;

    $aleph = new AlephOracle(AlephOracle::LIVE);

    $count = $aleph->querySingle($csql, $bind);
    if ($count >= 50000){
      throw new Exception("This query resulted in more than 50,000 items.  Add more filters, or contact the office for longer lists.");
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
