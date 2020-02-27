<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';

header('Content-type: application/json; charset=utf-8');

$validBudgets = AlephData::budgets();
if (empty($validBudgets)){
  header('HTTP/1.1 500 Internal Server Error');
  die(json_encode(array('error' => 'Fetching init data failed.')));
}

$orderUnit = strtoupper($_GET['order-unit']);
if (empty($orderUnit) || !in_array($orderUnit, array_keys($validBudgets))){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error' => 'Invalid order unit code', 'valid' => array_keys($validBudgets))));
}

$year = $_GET['budget-year'];
if (empty($year) || !in_array($year, array_keys($validBudgets[$orderUnit]))){
  header('HTTP/1.1 400 Bad Request');
  die(json_encode(array('error' => 'Invalid year', 'valid' => array_keys($validBudgets[$orderUnit]))));
}

$budgetNumbers = array();
foreach($validBudgets[$orderUnit][$year] as $budget => $name){
  $budgetNumbers[] = $budget . "-" . $year;
}

try{
  $cache = new ReportsCache(basename(__DIR__));
  $maxAge = $year < date('Y')-1 ? 'P1Y' : 'P7D';
  if ($cache->isStale($maxAge)){

    $budgetString = "('" . join("','", $budgetNumbers) . "')";
    $sql = str_replace(":BUDGETS", $budgetString, file_get_contents('./query.sql'));
    $aleph = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $aleph->query($sql, $bind),
      $aleph->querySingle("SELECT TO_CHAR(MAX(last_mviews_refresh), 'YYYY-MM-DD HH24:MI:SS') FROM webreport.last_mviews_refresh")
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
