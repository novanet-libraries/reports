<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';
require 'AlephData.class.php';

header('Content-type: application/json; charset=utf-8');

$validBudgets = AlephData::budgets();
if (empty($validBudgets)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

$orderUnit = $_GET['order-unit'];
$year      = $_GET['budget-year'];
$budgets   = [];

if (empty($orderUnit) || !in_array($orderUnit, array_keys($validBudgets))){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(array('error' => 'Invalid order unit code'));
  die();
}
if (empty($year) || !in_array($year, array_keys($validBudgets[$orderUnit]))){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(array('error' => 'Invalid year'));
  die();
}

$validCodes = array_keys($validBudgets[$orderUnit][$year]);
foreach($_GET['budget-number'] as $budget){
  if (!in_array($budget, $validCodes)){
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(array('error' => 'Invalid budget number'));
    die();
  }
  $budgets[] = "$budget-$year";
}

try{
  $cache = new ReportsCache(basename(__DIR__));
  $maxAge = $year < date('Y')-1 ? 'P1Y' : 'P7D';
  if ($cache->isStale($maxAge)){
    $sql  = file_get_contents('./query.sql');

    //replace IN ( :BUDGETNUMBERS ) with in IN (:B0, :B1, :B2, etc.)
    $bind = array();
    foreach($budgets as $idx => $code){
      $bind[":B$idx"] = $code;
    }
    $sql = str_replace(":BUDGETNUMBERS", join(",", array_keys($bind)), $sql);

    $aleph = new AlephOracle(AlephOracle::LIVE);
    $cache->refresh(
      $aleph->query($sql, $bind),
      $aleph->querySingle('SELECT MAX(last_mviews_refresh) FROM webreport.last_mviews_refresh;')
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
