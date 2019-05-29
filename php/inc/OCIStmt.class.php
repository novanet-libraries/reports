<?php

// creates a prepared statment that does not return results (e.g. insert or update)
// pass mode as OCI_NO_AUTO_COMMIT to run it within a transaction.
// (then run oci_commit() or oci_rollback() afterward)

class OCIStmt {
  private $parsedStmt;
  private $parameters;
  private $mode;
  
  public function __construct($connection, $sql, $mode = OCI_COMMIT_ON_SUCCESS){
    if ($mode != OCI_COMMIT_ON_SUCCESS && $mode != OCI_NO_AUTO_COMMIT){
      throw new Exception("Did not recoginize mode parameter.");
    }
    $this->mode = $mode;
    
    $this->parsedStmt = oci_parse($connection, $sql);
    if (!$this->parsedStmt){
      $e = oci_error($connection);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }
    
    $this->parameters = array();
  }

  public function bindArg($name, $value){
    $this->parameters[$name] = $value;
    $success = oci_bind_by_name($this->parsedStmt, $name, $this->parameters[$name]);
    if (!$success){
      $e = oci_error($this->parsedStmt);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }
  }
  public function bindArgs($args){
    foreach($args as $name => $value){
      $this->bindArg($name, $value);
    }
  }
  
  public function execute($args = null, $mode = null){
    if ($args){
      $this->bindArgs($args);
    }
    $success = oci_execute($this->parsedStmt, ($mode?$mode:$this->mode));
    if (!$success){
      $e = oci_error($this->parsedStmt);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }
  }

}
