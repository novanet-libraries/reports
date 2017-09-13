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
$budget = $_REQUEST['budget-number'];

if (empty($orderUnit) || !in_array($orderUnit, array_keys($validBudgets))){
  header('HTTP/1.1 400 Bad Request');
  $output = array('error' => 'Invalid order unit code', 'valid' => array_keys($validBudgets));
}
else if (empty($year) || !in_array($year, array_keys($validBudgets[$orderUnit]))){
  header('HTTP/1.1 400 Bad Request');
  $output = array('error' => 'Invalid year', 'valid' => array_keys($validBudgets[$orderUnit]));
}
else if (empty($budget) || !in_array($budget, array_keys($validBudgets[$orderUnit][$year]))){
  header('HTTP/1.1 400 Bad Request');
  $output = array('error' => 'Invalid budget number', 'valid' => array_keys($validBudgets[$orderUnit][$year]));
}
else{
  try{
    $cache = new ReportsCache('spent_ytd_list');
    $maxAge = $year < date('Y')-1 ? 'P1Y' : 'P7D';
    if ($cache->isStale($maxAge)){
      $sql  = file_get_contents('./spent-ytd-list.sql');
      $bind = array(
        ':ORDERUNIT'    => $orderUnit,
        ':BUDGETNUMBER' => "$budget-$year"
      );      
      $db = new AlephOracle(AlephOracle::TEST);
      $results = array();
      foreach($db->query($sql, $bind) as $row){
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
