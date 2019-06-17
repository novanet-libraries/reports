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

    //guess the current budget year from the current date
    $t = (new DateTime())->sub(new DateInterval('P3M'));
    $budgetYear = $t->format('Y');
    
    $bind = array(); 
    $bind[":ORDERUNIT"] = $orderUnit;
    $bind[":BUDGETYEAR"] = $budgetYear;

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
