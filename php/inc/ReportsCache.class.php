<?php

/*
**  CREATE DATABASE reports_cache DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci;
**
**  CREATE TABLE cache_state (param_string VARCHAR(255) PRIMARY KEY, last_update DATETIME DEFAULT NULL);
**
**  --- Then create one table for each report.
**  --- Each 'report' table should match the columns that are fetched out of Oracle and displayed,
**  --- plus a param_string column that is a foreign key to the cache_state table
**  --- All logic and constraints go into creating the table in Oracle.
**  --- The cache is just a dead-simple 2D view of the output.
**
**  --- e.g.
**  CREATE TABLE invalid_sublibraries (
**      param_string varchar(255),
**      Z30_SUB_LIBRARY VARCHAR(6),
**      C INTEGER
**      FOREIGN KEY (param_string) REFERENCES cache_state(param_string) ON DELETE CASCADE
**  );
**
**  Javascript/DataTables get column names from here, so the names matter.  When you select out of
**  Oracle, alias the column names to the actual column name here.  When you fetch column info in
**  javascript, look for these column names.
*/

class ReportsCache {

  public function __construct($tableName){
    try{
      $this->connect();
      $this->getTableInfo($tableName);
      $this->getRequestInfo();
      $this->getLastUpdated();
    }
    catch (Exception $ex){
      throw new Exception($ex->getMessage());
    }
  }

  //to pass in a different $maxAge, use the ISO 8601 Duration format:
  //http://en.wikipedia.org/wiki/ISO_8601#Durations
  public function isStale($maxAgeOverride = null){
    try{
      if ($maxAgeOverride){
        $this->maxAge = new DateInterval($maxAgeOverride);
      }
    } catch(Exception $ex) {
      //log this, but just carry on.
      trigger_error("Tried to override maxAge and failed: $maxAgeOverride", E_USER_WARNING);
    }

    if ($this->forceRefresh || empty($this->lastUpdate)){
      return true;
    }
    else{
      $now = new DateTime();
      $last_plus_max = (new DateTime($this->lastUpdate))->add($this->maxAge);
      return ($last_plus_max < $now);
    }
  }

  //if you start writing (echo), then encounter an error, you can't send error header to the client
  //so the catch() block here doesn't work the way we'd like it to.
  public function writeJSON(){
    try{
      $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE param_string = ?");
      $stmt->execute(array($this->paramString));
      
      echo '{', json_encode("date"), ':', json_encode($this->lastUpdate);
      echo ',', json_encode("data"), ':[';
      foreach($stmt as $idx => $row){
        if ($idx != 0){
          echo ',';
        }
        echo json_encode($row);
      }
      echo ']}';
    }
    catch (Exception $ex){
      error_log($ex->getMessage());
      throw new Exception("Error writing from cache: " . $this->table . " - " .  $this->paramString);
    }
  }
  
  public function fetch(){
    try{
      $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE param_string = ?");
      $stmt->execute(array($this->paramString));
      $data = $stmt->fetchAll();

      return array(
        "date" => $this->lastUpdate,
        "data" => $data
      );
    }
    catch (Exception $ex){
      error_log($ex->getMessage());
      throw new Exception("Error fetching from cache: " . $this->table . " - " .  $this->paramString);
    }
  }

  //input data must be an array and each element must be an array keyed by column name.
  public function refresh($data = null){
    try{
      //REPLACE INTO is "delete then insert", so this statement will invoke
      //the ON DELETE CASCADE clause in $this->table to clean out any old data.
      $cacheStmt = $this->db->prepare("REPLACE INTO cache_state (param_string, last_update) VALUES (?,?)");
      if (!empty($data)){
        $colString  =       join(", ", $this->columns);
        $valString  = ":" . join(",:", $this->columns);
        $insertStmt = $this->db->prepare("INSERT INTO {$this->table} ( {$colString} ) VALUES ( {$valString} )");
      }

      $now = date("Y-m-d H:i:s");

      $this->db->beginTransaction();
      $cacheStmt->execute(array($this->paramString, $now));
      if (!empty($data)){
        foreach($data as $row){
          $row["param_string"] = $this->paramString;
          $insertStmt->execute($row);
        }
      }
      $this->db->commit();

      $this->lastUpdate = $now;
    }
    catch (Exception $ex){
      if ($this->db->inTransaction()){
        $this->db->rollback();
      }
      error_log($ex->getMessage());
      throw new Exception("Error updating the cache: " . $this->table . " - " . $this->paramString);
    }
  }



  private $db;           //resource
  private $table;        //string, table name
  private $columns;      //array of strings, column names
  private $paramString;  //string, primary key
  private $maxAge;       //DateInterval
  private $lastUpdate;   //string "YYYY-MM-DD HH:mm:ss"
  private $forceRefresh; //boolean

  private function connect(){
    $conf = parse_ini_file(stream_resolve_include_path("ReportsCache.ini"), true);
    $user = $conf["Credentials"]["user"];
    $pass = $conf["Credentials"]["pass"];
    $dsnParts = array();
    foreach($conf["DataSourceName"] as $key => $val){
      $dsnParts[] = "$key=$val";
    }
    $dsn = "mysql:" . join(";", $dsnParts);

    try{
      $this->db = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 1,
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8;"
      ));
    }
    catch (Exception $ex){
      error_log($ex->getMessage());
      throw new Exception("Error connecting to the cache.");
    }
  }

  //set $this->table and $this->columns
  private function getTableInfo($tableName){
    $tableName = strtolower(str_replace("-", "_", trim($tableName)));
    if ($tableName == "cache_state"){
      throw new InvalidArgumentException("Invalid table name (cache_state is a reserved word).");
    }
    try{
      $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(array("reports_cache", $tableName));
      $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
      if (empty($result)){
        throw new Exception("$tableName is not a cached report");
      }
      $this->table   = $tableName;
      $this->columns = $result;
    }
    catch (Exception $ex){
      error_log($ex->getMessage());
      throw new Exception("Error getting cache details for " . $tableName);
    }
  }

  //set paramString, maxAge, and forceRefresh.
  //paramString is the same pretty URL that fetched the report in the first place
  //it must be unique per requested report; it is the primary key in the cache_state table.
  //paramString could have user-entered data in it; use prepared statements when using it in SQL.
  private function getRequestInfo(){
    $in = $_GET ?: array();

    //jQuery adds this parameter with a timestamp to indicate it does not want cached results.
    $this->forceRefresh = !empty($in["_"]);
    unset($in["_"]);//this lets anyone benefit from new results in the cache. (i.e. don't store this param as part of the param_string key)

    $this->maxAge = new DateInterval("P1D");
    if (!empty($in["max-age"])){
      try{
        if (ctype_digit($in["max-age"])){
          $in["max-age"] = "PT" . $in["max-age"] . "S";
        }
        $this->maxAge = new DateInterval($in["max-age"]);
      }
      catch (Exception $ex){
        //log this, but just keep the default P1D.
        trigger_error("Tried to initialize maxAge and failed: " . $_GET["max-age"], E_USER_WARNING);
      }
    }
    unset($in["max-age"]);//this lets anyone benefit from new results in the cache. (i.e. don't store this param as part of the param_string key)

    $params = array();
    ksort($in);
    foreach($in as $key => $val){
      if (is_array($val)){
        $val = array_values($val);
        sort($val);
        $val = join(",", $val);
        $key.= "[]";
      }
      $params[] = $key;
      $params[] = $val;
    }

    //PHP_SELF is stored in a directory like /something/something/category/reportname/report.php
    //we want category and reportname in the fixedParams.
    $fixedParams = join("/", array_splice(explode("/", $_SERVER['PHP_SELF']), -3, 2));
    $this->paramString = "/" . $fixedParams . "/" . join("/", $params) . "/";

    //we'd like to have a descriptive key, but if that's too long then a unique key will be good enough.
    if (strlen($this->paramString) > 255){
      $this->paramString = "/" . $fixedParams . "/" . md5($this->paramString) . "/";
    }
  }

  private function getLastUpdated(){
    try {
      $stmt = $this->db->prepare("SELECT last_update FROM cache_state WHERE param_string = ?");
      $stmt->execute(array($this->paramString));
      $this->lastUpdate = $stmt->fetchColumn(0);
    }
    catch (Exception $ex){
      error_log($ex->getMessage());
      throw new Exception("Error getting last update date for " . $this->table);
    }
  }
}
