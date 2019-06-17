<?php

header('Content-type: application/json');

require('AlephData.class.php');
$valid = array();
$obj = new ReflectionClass('AlephData');
foreach($obj->getMethods(ReflectionMethod::IS_PUBLIC) as $method){
  $valid[] = $method->name;
}

if (isset($_GET["type"]) && in_array($_GET["type"], $valid)){
  $method = $_GET["type"];
  echo json_encode(AlephData::$method());
}
else{
  $allData = array();
  foreach($valid as $method){
    $allData[$method] = AlephData::$method();
  }
  echo json_encode($allData);
}
