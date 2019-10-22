<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';
require 'CallNumber.class.php';

header("Content-type: application/json");

$startDate    = null;
$endDate      = null;
$sublibraries = [];
$cnRanges     = [];
$dateRanges   = [];
$periodSplit  = false;

//these names must match events returned by query.sql
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
  if (empty($_GET['begin']) || empty($_GET['end'])){
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
  $startDate = new DateTime($_GET['begin']);
  $endDate   = new DateTime($_GET['end']);
  if ($endDate < $startDate){
    $swap = $endDate;
    $endDate = $startDate;
    $startDate = $swap;
  }
  $now = new DateTime();
  $sixYearsAgo = (new DateTime())->sub(new DateInterval('P6Y'));
  if ($startDate < $sixYearsAgo || $endDate > $now){
    throw new Exception("Date range out of bounds");
  }

  //we just need the string from now on, not the DateTime object.
  $startDate = $startDate->format('Ymd');
  $endDate   = $endDate->format('Ymd');

  //set $dateRanges
  if (isset($_GET['periodSplit']) && $_GET['periodSplit'] != 'N'){
    $periodSplit = true;
    $yearType = 'C';
    if ($startDate->format('n') == '4'){
      $yearType = 'F';
    }
    if ($startDate->format('n') == '9'){
      $yearType = 'A';
    }
    $dateRanges = subdivideDateRange($startDate, $endDate, $yearType);
  }
  else{
    $dateRanges = array(array('start' => $startDate, 'end' => $endDate, 'label' => "$startDate-$endDate"));
  }
  
  foreach($_GET['range'] as $rangeString){
    $pair = parseCNInput($rangeString);
    if (empty($pair)){
      throw new Exception("Invalid callnumber range");
    }
    $cnRanges[$rangeString]['bounds'] = $pair;
    foreach($dateRanges as $period){
      $cnRanges[$rangeString][$period['label']] = $events;
    }
  }
  foreach($dateRanges as $period){
    $cnRanges['Other LC'][$period['label']] = $events;
    $cnRanges['Non LC'][$period['label']]   = $events;
  }
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

    if ($periodSplit){
      $caseParts = array("CASE");
      foreach($dateRanges as $r){
        $caseParts[] = "WHEN Z35_EVENT_DATE BETWEEN {$r['start']} AND {$r['end']} THEN '{$r['label']}'";
      }
      $caseParts[] = "ELSE 'Other' END AS PERIOD";
      $period = join(" ", $caseParts);
    }
    else{
      $period = "'{$dateRanges[0]['label']}' AS PERIOD";
    }
    $sql = str_replace(":PERIOD", $period, $sql);

    $aleph = new AlephOracle(AlephOracle::LIVE);
    foreach($aleph->query($sql, $bind) as $row){
      try {
        $cn = new CallNumber($row['CALLNUMBER']);
        $counted = false;
        foreach($cnRanges as $label => $rangeInfo){
          if (isset($rangeInfo['bounds'])){
            if ($cn->compareTo($rangeInfo['bounds'][0]) > -1 && $cn->compareTo($rangeInfo['bounds'][1]) < 1){
              $cnRanges[$label]['events'][$row['PERIOD']][$row['EVENT']]++;
              $counted = true;
            }
          }
        }
        if (!$counted){
          $cnRanges['Other LC']['events'][$row['PERIOD']][$row['EVENT']]++;
        }
      }
      catch (Exception $ex){
        $cnRanges['Non LC']['events'][$row['PERIOD']][$row['EVENT']]++;
      }
    }

    $data = [];
    foreach($cnRanges as $label => $rangeInfo){
      $row = array("CNRANGE" => $label, "CNRANGE2" => "");
      if (isset($rangeInfo['bounds'])){
        $row["CNRANGE2"] = $rangeInfo['bounds'][0] . " - " . $rangeInfo['bounds'][1];
      }
      else if ($label == 'Non LC'){
        $row["CNRANGE2"] = "Items with non-LC callnumbers";
      }
      else if ($label == 'Other LC'){
        $row["CNRANGE2"] = "Items with LC callnumbers outside the specified ranges";
      }

      foreach($rangeInfo['events'] as $period => $eventCounts){
        $row['PERIOD'] = $period;
        foreach(array_keys($events) as $eventName){
          $row[$eventName] = $eventCounts[$eventName];
        }
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

//subdivide date range into 'buckets'
function subdivideDateRange($start, $end, $yearType = 'C'){
  try{ //parse arguments

    $startDate = new DateTime($start);
    $endDate   = new DateTime($end);
    if ($endDate < $startDate){
      throw new Exception('End date was before start date');
    }

    $yearType = strtoupper(substr(ltrim($yearType)), 0, 1);
    if ($yearType != 'C' && $yearType != 'F' && $yearType != 'A'){
      //$yearType = 'C';
      throw new Exception('Invalid year type');
    }

  }catch(Exception $ex){
    throw new InvalidArgumentException($ex->getMessage());
  }

  $ranges = array(); //return value
  $oneDay = new DateInterval('P1D');
  $ymdFmt = 'Ymd';

  $diff  = $startDate->diff($endDate)->days;

  if ($diff < 15){
    $interval   = $oneDay;
    $labelFmt   = 'M j Y';
    $rangeStart = $startDate;
  }
  else if ($diff < 100){
    $interval   = new DateInterval('P1W');
    $labelFmt   = '\W\e\e\k \o\f M j Y';
    $rangeStart = clone $startDate;
    $rangeStart->modify('monday this week');
  }
  else if ($diff < 500){
    $interval   = new DateInterval('P1M');
    $labelFmt   = 'M Y';
    $rangeStart = clone $startDate;
    $rangeStart->modify('first day of this month');
  }
  else{
    switch($yearType){
      case 'F':
        $labelFmt  = '\F\YY';
        $monthName = 'april';
        $mod       = ($startDate->format('n') >= 4) ? 'this' : 'last';
      break;
      case 'A':
        $labelFmt  = '\A\YY';
        $monthName = 'september';
        $mod       = ($startDate->format('n') >= 9) ? 'this' : 'last';
      break;
      case 'C':
      default:
        $labelFmt  = 'Y';
        $monthName = 'january';
        $mod       = 'this';
      break;
    }
    $interval   = new DateInterval('P1Y');
    $rangeStart = clone $startDate;
    $rangeStart->modify("$monthName $mod year");
    $rangeStart->modify('first day of this month');
  }

  while ($rangeStart <= $endDate){

    $rangeEnd = clone $rangeStart;
    $rangeEnd->add($interval)->sub($oneDay);

    $maxStart = ($startDate > $rangeStart) ? $startDate : $rangeStart;
    $minEnd   = ($endDate   < $rangeEnd  ) ? $endDate   : $rangeEnd;

    $ranges[] = array(
      'label' => $rangeStart->format($labelFmt),
      'start' => $maxStart->format($ymdFmt),
      'end'   => $minEnd->format($ymdFmt)
    );

    $rangeStart->add($interval);
  }

  return $ranges;
}
