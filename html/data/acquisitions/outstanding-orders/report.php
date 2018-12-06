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

if (empty($orderUnit) || !in_array($orderUnit, array_keys($validBudgets))){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(array('error' => 'Invalid order unit code', 'valid' => array_keys($validBudgets)));
  die();
}

try{
  $cache = new ReportsCache(basename(__DIR__));
  if ($cache->isStale()){
    $sql  = file_get_contents('./query.sql');

    $t = (new DateTime())->sub(new DateInterval('P3M'));
    $budgetYear = $t->format('Y');
    
    $bind = array(); 
    $bind[":ORDERUNIT"] = $orderUnit;
    $bind[":BUDGETYEAR"] = $budgetYear;

    $db = new AlephOracle(AlephOracle::LIVE);
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

echo json_encode($output);
