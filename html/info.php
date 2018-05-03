<?php
phpinfo();

register_shutdown_function(function(){
  $error = error_get_last();
  if (null !== $error){
    //mail('brad.dewar@novanet.ca', 'PHP Error in reports.novanet.ca', $_SERVER['REQUEST_URI'] . "\n\n" . print_r($error, true));
    echo "Entered shutdown function\n";
    print_r($error);
   // if ($error['type'] in_array($error['type'], [1, 4, 16, 32, 
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(
      array('error' => 'The server did not respond with any data.  Brad was notified about the error', 'date' => date('Y-m-d H:i:s'))
    );
  }
});

/* try {
  trigger_error("Error Message", E_USER_WARNING);
  throw new Exception("Testing in Loop\n");
}catch (Exception $ex){
  header("HTTP/1.1 500 Internal Server Error");
  echo "Caught\n";
}

echo "fin"; */
echo '<pre>';
$e_constants = array_filter(get_defined_constants(true)["Core"], function($key){
  echo $key, " ", substr($key,0,2), "\n";
  return (substr($key,0,2) == "E_");
}, ARRAY_FILTER_USE_KEY);
//$e_types = array_flip($e_constants);

print_r($e_constants);
