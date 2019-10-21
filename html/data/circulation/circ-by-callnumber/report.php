<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';
require 'CallNumber.class.php';

header("Content-type: application/json");

$startDate    = null;
$endDate      = null;
$sublibraries = [];
$cnranges     = [];

$events = array(
  'LOAN'      => 0,
  'RENEWAL'   => 0,
  'HOLD'      => 0,
  'PHOTOCOPY' => 0,
  'RESHELF'   => 0,
  'BOOKING'   => 0
);
$validSublibraries = AlephData::sublibraries();
$prohibited = '/^NOVA|WWW|.+NET|.+BK$/';
if (empty($validSublibraries)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

try{

  if (empty($_GET['sublibrary'])){
    throw new Exception('Must provide sublibrary code');
  }
  if (empty($_GET['start-date']) || empty($_GET['end-date'])){
    throw new Exception('Must provide a date range');
  }
  if (empty($_GET['range'])){
    throw new Exception('Must provide callnumber range(s)');
  }
  
  $validCodes = array_keys($validSublibraries);
  foreach($_GET['sublibrary'] as $input){
    $sublibrary = strtoupper($input);
    if (!in_array($sublibrary, $validCodes) || preg_match($prohibited, $sublibrary)){
      throw new Exception('Invalid sublibrary code');
    }
    $sublibraries[] = $sublibrary;
  }

  //DateTime constructor will throw if we receive invalid date strings.
  $startDate = new DateTime($_GET['start-date']);
  $endDate   = new DateTime($_GET['end-date']);
  if ($endDate->diff($startDate) > (new DateInterval('P1Y'))){
    throw new Exception("Date range too large");
  }
  
  $startDate = $startDate->format('Ymd');
  $endDate   = $endDate->format('Ymd');
  
  foreach($_GET['range'] as $cnrange){
    $pair = parseCNInput($cnrange);
    if (empty($pair)){
      throw new Exception("Invalid callnumber range");
    }
    $cnranges[$cnrange]['bounds'] = $pair;
    $cnranges[$cnrange]['events'] = $events;
  }
  $cnranges['Other LC']['events'] = $events;
  $cnranges['Non LC']['events']   = $events;
}
catch (Exception $ex){
  header("HTTP/1.1 400 Bad Request");
  echo json_encode(array('error' => $ex->getMessage()));
  die();
}

try{

  $cache = new ReportsCache(basename(__DIR__));
  if ($cache->isStale()){
    
    $sql  = file_get_contents('./query.sql');
    $bind = array();
    foreach($sublibraries as $idx => $code){
      $bind[":SUBLIB$idx"] = $code;
    }
    $sql = str_replace(":SUBLIBRARIES", join(",", array_keys($bind)), $sql);
    $bind[":STARTDATE"] = $startDate;
    $bind[":ENDDATE"]   = $endDate;

    $aleph = new AlephOracle(AlephOracle::LIVE);
    foreach($aleph->query($sql, $bind) as $row){
      try {
        $cn = new CallNumber($row['CALLNUMBER']);
        $counted = false;
        foreach($cnranges as $label => $rangeInfo){
          if (isset($rangeInfo['bounds'])){
            if ($cn->compareTo($rangeInfo['bounds'][0]) > -1 && $cn->compareTo($rangeInfo['bounds'][1]) < 1){
              $cnranges[$label]['events'][$row['EVENT']]++;
              $counted = true;
            }
          }
        }
        if (!$counted){
          $cnranges['Other LC']['events'][$row['EVENT']]++;
        }
      }
      catch (Exception $ex){
        $cnranges['Non LC']['events'][$row['EVENT']]++;
      }
    }

    $data = [];
    foreach($cnranges as $label => $info){
      $row = array("CNRANGE" => $label);
      foreach(array_keys($events) as $evt){
        $row[$evt] = $info['events'][$evt];
      }
      $data[] = $row;
    }
      
    $cache->refresh($data);
  }

  $output = $cache->fetch();
}
catch (Exception $ex){
  error_log($ex->getMessage());
  header('HTTP/1.1 500 Internal Server Error');
  $output = array('error' => $ex->getMessage());
}

echo json_encode($output);


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
