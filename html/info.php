<?php
phpinfo();

register_shutdown_function(function(){
  $error = error_get_last();
  if (null !== $error){
    mail('brad.dewar@novanet.ca', 'PHP Error in reports.novanet.ca', $_SERVER['REQUEST_URI'] . "\n\n" . print_r($error, true));
    //echo "Entered shutdown function\n";
    //print_r($error);
    // if ($error['type'] in_array($error['type'], [1, 4, 16, 32, 
    //header('HTTP/1.1 500 Internal Server Error');
    //echo json_encode(
    //  array('error' => 'The server did not respond with any data.  Brad was notified about the error', 'date' => date('Y-m-d H:i:s'))
    //);
  }
});
