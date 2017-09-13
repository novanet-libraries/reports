<?php

require 'OCIQuery.class.php';

class AlephOracle {
  const LIVE = 1;
  const TEST = 2;
  const READONLY = 3;
  const READWRITE = 4;

  private $connection;

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
      throw new Exception($e['message'] . "\n" . $conf['user'] . "/******@" . $conf['host']);
    }
  }

  //returns an iterable result set; each iteration is an associative array representing one row
  // in the queried table.  (col_name_1 => value, col_name_2 => value, ...)
  public function query($sql, $bindArgs=null){
    $stmt = new OCIQuery($this->connection, $sql, $bindArgs);
    return $stmt;
  }

  //If you want to do more than OCIQuery, you can use the php OCI8 api on this:
  public function directConnectionAccess(){
    return $this->connection;
  }
}

