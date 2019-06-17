<?php

require 'OCIQuery.class.php';
require 'OCIStmt.class.php';

class AlephOracle {
  const LIVE = 1;
  const TEST = 2;
  const READONLY = 3;
  const READWRITE = 4;

  private $connection;
  private $mode = OCI_COMMIT_ON_SUCCESS;

  public function __construct($instance= self::TEST, $level = self::READONLY){

    switch ($instance){
      case self::LIVE:
        $conf = parse_ini_file(stream_resolve_include_path('AlephOracleLive.ini'));
        break;
      case self::TEST:
        $conf = parse_ini_file(stream_resolve_include_path('AlephOracleTest.ini'));
        break;
      default:
        throw new Exception("Must specify instance (test or live).");
    }

    if ($level == self::READWRITE){
      //switch to more privileged user
      $conf['user'] = $conf['write-user'];
      $conf['pass'] = $conf['write-pass'];
    }

    $this->connection = oci_connect($conf['user'], $conf['pass'], $conf['host'], 'AL32UTF8');
    if (!$this->connection){
      $e = oci_error();
      throw new Exception($e['message'] . "\nwith connectstring: " . $conf['user'] . "/******@" . $conf['host']);
    }
  }

  public function prepare($sql){
    $stmt = new OCIStmt($this->connection, $sql, $this->mode);
    return $stmt;
  }
  
  //returns an iterable result set; each iteration is an associative array representing one row
  // in the queried table.  (col_name_1 => value, col_name_2 => value, ...)
  public function query($sql, $bindArgs=null){
    $stmt = new OCIQuery($this->connection, $sql, $bindArgs, $this->mode);
    return $stmt;
  }
  
  //prepares and returns a query without executing it
  public function prepareQuery($sql){
    $stmt = new OCIQuery($this->connection, $sql, null, $this->mode, false);
    return $stmt;    
  }
  
  //returns a single value if the 'select' part of the input query contains a single column
  //otherwise returns an array representing a single row of results.
  public function querySingle($sql, $bindArgs=null){
    $q = $this->prepareQuery($sql);
    return $q->result($bindArgs);
  }
  
  //transaction control
  public function begin(){
    $this->mode = OCI_NO_AUTO_COMMIT;
  }
  public function commit(){
    $this->mode = OCI_COMMIT_ON_SUCCESS;
    $success = oci_commit($this->connection);
    if (!$success){
      $e = oci_error($this->connection);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }
  }
  public function rollback(){
    $this->mode = OCI_COMMIT_ON_SUCCESS;
    $success = oci_rollback($this->connection);
    if (!$success){
      $e = oci_error($this->connection);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }
  }

  //If you want to do more, you can use the php OCI8 api on this:
  public function directConnectionAccess(){
    return $this->connection;
  }
}

