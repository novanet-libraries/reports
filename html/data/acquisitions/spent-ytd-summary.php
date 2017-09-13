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

$orderUnit = strtoupper($_REQUEST['order-unit']);
$year = $_REQUEST['budget-year'];

if (empty($orderUnit) || !in_array($orderUnit, array_keys($validBudgets))){
  header('HTTP/1.1 400 Bad Request');
  $output = array('error' => 'Invalid order unit code', 'valid' => array_keys($validBudgets));
}
else if (empty($year) || !in_array($year, array_keys($validBudgets[$orderUnit]))){
  header('HTTP/1.1 400 Bad Request');
  $output = array('error' => 'Invalid year', 'valid' => array_keys($validBudgets[$orderUnit]));
}
else{
  try{
    $cache = new ReportsCache('spent_ytd_summary');
    $maxAge = $year < date('Y')-1 ? 'P1Y' : 'P7D';
    if ($cache->isStale($maxAge)){
      $budgetNumbers = array();
      foreach($validBudgets[$orderUnit][$year] as $budget => $name){
        $budgetNumbers[] = $budget . "-" . $year;
      }
      $budgetString = "('" . join("','", $budgetNumbers) . "')";
      $sql = str_replace(":BUDGETS", $budgetString, file_get_contents('./spent-ytd-summary.sql'));
      $db = new AlephOracle(AlephOracle::TEST);
      $results = array();
      foreach($db->query($sql) as $row){
        $results[] = $row;
      }
      $cache->refresh($results);
      
      $output = array(
        'date' => date('Y-m-d H:i:s'),
        'data' => $results
      );
    }
    else{
      $output = $cache->fetch();
    }
  }
  catch (Exception $ex){
    error_log($ex->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    $output = array('error' => $ex->getMessage());
  }
}

echo json_encode($output);
