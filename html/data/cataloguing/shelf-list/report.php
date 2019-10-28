<?php

require('AlephData.class.php');
require('AlephOracle.class.php');
require('ReportsCache.class.php');
require('CallNumber.class.php');

header('Content-type: application/json; charset=utf-8');

//validate input
$sublibrary  = null;
$collections = array();
$cnRanges    = array();
$inputErrors = array();
$prohibited  = '/^(.+NET|.+RES)$/';
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

if (!empty($_GET['range'])){
  foreach($_GET['range'] as $rangeString){
    $pair = parseCNInput($rangeString);
    if (empty($pair)){
      throw new Exception("Invalid callnumber range");
    }
    $cnRanges[$rangeString]['bounds'] = $pair;
    $cnRanges[$rangeString]['alpha']  = array(
      substr($pair[0], 0, strcspn($pair[0], '1234567890. ')),
      substr($pair[1], 0, strcspn($pair[1], '1234567890. ')).'Z'
    );
  }
}

try{
  $cache = new ReportsCache(basename(__DIR__));

  if ($cache->isStale()){
    $sql  = file_get_contents("./query.sql");

    //replace IN ( :COLLECTION ) with in IN (:COL0, :COL1, :COL2, etc.)
    $bind = array();
    foreach($collections as $idx => $code){
      $bind[":COL$idx"] = $code;
    }
    $sql  = str_replace(":COLLECTIONS", join(",", array_keys($bind)), $sql);

    $bind[":SUBLIB"] = $sublibrary;
    
    if (!empty($cnRanges)){
      $orParts = array();
      foreach($cnRanges as $label => $rangeInfo){
        $orParts[] = "(CALLNUMBER >= '{$rangeInfo['alpha'][0]}' AND CALLNUMBER <= '{$rangeInfo['alpha'][1]}')";
      }
      $sql = str_replace(":ANDCN", "AND (" . join(" OR ", $orParts) . ")", $sql);        
    }
    else{
      $sql = str_replace(":ANDCN", "", $sql);
    }

    $aleph = new AlephOracle(AlephOracle::LIVE);

    $csql = preg_replace('/\bSELECT\b.+?\bFROM\b/is', 'SELECT count(*) FROM', $sql, 1);
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




//accept the way librarians write CN ranges
// return an actual CN range understandable by the program.
function parseCNInput($input){
  $input = trim($input);
  $input = trim($input, '-');
  $parts = [];
  $match = [];
  if (preg_match('/^([A-Z]{1,3}\s{0,3}(?:[1-9][0-9\.]{0,8})?)(?:\s{0,3}-\s{0,3}([A-Z]{0,3}\s{0,3}(?:[1-9][0-9\.]{0,8})?))?$/', $input, $match)){
    //last two members of $match will either be 1st and 2nd captured groups (start and end of a range separated by a hyphen)
    //or the full match and the first captured group (single CN with no hyphen, so it is both the start and the end)
    $parts = array_splice($match, -2);
    $parts[0] = preg_replace('/[^A-Z0-9\.]/', '', $parts[0]);
    $parts[1] = preg_replace('/[^A-Z0-9\.]/', '', $parts[1]);

    if (ctype_digit($parts[1])){
      //prepend the letters in $parts[0] to $parts[1]
      $alpha = substr($parts[0], 0, strcspn($parts[0], '1234567890. '));
      $parts[1] = $alpha . $parts[1];
    }

    if (ctype_upper($parts[1]) && strlen($parts[1]) < 2){
      $parts[1] .= "Z";
    }
    if (ctype_upper($parts[1])){
      $parts[1] .= '99999.9999';
    }
    else if (ctype_alnum($parts[1])){
      $parts[1] .= '.99999';
    }

    $parts[1] .= ' .ZZ99999';

    if (ctype_upper($parts[0])){
      $parts[0] .= '1';
    }
    $parts[0] .= ' .A1';

    //this might throw; it will result in HTTP/1.1 400 Bad Request.
    $parts[0] = new CallNumber($parts[0]);
    $parts[1] = new CallNumber($parts[1]);
    return $parts;
  }
  else{
    return false;
  }
}
