<?php
/*
** AlephData is a class that fetches configuration data from the Aleph server.
** This is simply a proxy so that the data can be fetched client side from the local server.
*/

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
