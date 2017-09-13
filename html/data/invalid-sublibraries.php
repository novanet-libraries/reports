<?php

require 'ReportsCache.class.php';
require 'AlephOracle.class.php';

$cache = new ReportsCache();
$cachedTable = 'invalid_sublibraries';

header("Content-type: application/json");

if ($cache->needs_update($cachedTable)){
  
  $valid_sublibraries = array(
    'ACAD', 'ACABK', 'AST', 'CBU', 'CBUBK', 'CBU', 'CCAK', 'CCAV', 'CCBC', 'CCCU', 'CCWC', 'CCIT',
    'CCKC', 'CCLC', 'CCMC', 'CCPC', 'CCSA', 'CCSC', 'CCTR', 'CCNET', 'DLKIL', 'DLLAW', 'DLSXT',
    'DLWKK', 'DLAGR', 'DLNET', 'KINGS', 'MSVU', 'MSVBK', 'MSVU', 'NSCAD', 'NSHA', 'SFXCO',
    'SFXMD', 'SFXBK', 'SMU', 'SMUBK', 'USA', 'WWW', 'NOVA'
  );

  $sql = "SELECT Z30_SUB_LIBRARY, COUNT(*) AS C FROM NOV50.Z30 WHERE Z30_SUB_LIBRARY NOT IN ('";
  $sql .= join("','", $valid_sublibraries);
  $sql .= "') GROUP BY Z30_SUB_LIBRARY";

  try{
    $db = new AlephOracle(AlephOracle::LIVE);
    $results = array();
    foreach($db->query($sql) as $row){
      $results[] = $row;
    }
    $cache->refresh($cachedTable, $results);
    
    $output = array(
      'date' => array(
        'plain' => date('Y-m-d H:i:s'),
        'display' => date('M j, Y')
      ),
      'data' => $results
    );
  }
  catch (Exception $ex){
    error_log($ex->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    $output = array('error' => $ex->getMessage());
  }
}
else{
  $output = $cache->fetch($cachedTable);
}

echo json_encode($output);
