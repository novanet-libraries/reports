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
      $this->setTableInfo($tableName);
      $this->setReportInfo($tableName);
      $this->setMaxAge();
      $this->setParamString();
      $this->setLastUpdated();
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
  public function refresh($data = null, $lastUpdate = null){
    try{
      //REPLACE INTO is "delete then insert", so this statement will invoke
      //the ON DELETE CASCADE clause in $this->table to clean out any old data.
      $cacheStmt = $this->db->prepare("REPLACE INTO cache_state (param_string, last_update) VALUES (?,?)");
      if (!empty($data)){
        $colString  =       join(", ", $this->columns);
        $valString  = ":" . join(",:", $this->columns);
        $insertStmt = $this->db->prepare("INSERT INTO {$this->table} ( {$colString} ) VALUES ( {$valString} )");
      }

      if ($lastUpdate === null){
        $lastUpdate = date("Y-m-d H:i:s");
      }

      $this->db->beginTransaction();
      $cacheStmt->execute(array($this->paramString, $lastUpdate));
      if (!empty($data)){
        if (count($this->columns) != count($data[0]) + 1){
          error_log("Column count mismatch when refreshing cache for " . $this->table);
        }
        $row = array();
        $row["param_string"] = $this->paramString;
        foreach($data as $d){
          foreach($this->columns as $col){
            if ($col != 'param_string'){
              $row[$col] = isset($d[$col]) ? $d[$col] : null;
            }
          }
          $insertStmt->execute($row);
        }
      }
      $this->db->commit();

      $this->lastUpdate = $lastUpdate;
    }
    catch (Exception $ex){
      if ($this->db->inTransaction()){
        $this->db->rollback();
      }
      error_log($ex->getMessage());
      throw new Exception("Error updating the cache: " . $this->table . " - " . $this->paramString);
    }
  }



  private $config;       //array of config data read from ReportsCache.ini
  private $db;           //resource
  private $table;        //string, table name
  private $report;       //array of info, from DOCROOT/reports.json
  private $columns;      //array of strings, column names
  private $paramString;  //string, primary key
  private $maxAge;       //DateInterval
  private $lastUpdate;   //string "YYYY-MM-DD HH:mm:ss"
  private $forceRefresh; //boolean

  private function connect(){
    $this->config = parse_ini_file(stream_resolve_include_path("ReportsCache.ini"), true);
    $dsnParts = array();
    foreach($this->config["DataSourceName"] as $key => $val){
      $dsnParts[] = "$key=$val";
    }
    $dsn = "mysql:" . join(";", $dsnParts);
    $user = $this->config["Credentials"]["user"];
    $pass = $this->config["Credentials"]["pass"];
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
  private function setTableInfo($tableName){
    $tableName = strtolower(str_replace("-", "_", trim($tableName)));
    if ($tableName == "cache_state"){
      throw new InvalidArgumentException("Invalid table name (cache_state is a reserved word).");
    }
    try{
      $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(array($this->config["DataSourceName"]["dbname"], $tableName));
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

  private function setReportInfo($reportName){
    $reportName = strtolower(str_replace("_", "-", trim($reportName)));
    $allReports = json_decode(file_get_contents($_SERVER["DOCUMENT_ROOT"] . "/reports.json"), JSON_OBJECT_AS_ARRAY);
    if (isset($allReports[$reportName])){
      $this->report = $allReports[$reportName];
    }
    else{
      throw new Exception("Error getting report details for " . $reportName);
    }
  }

  //set maxAge and forceRefresh (maxAge = 0)
  private function setMaxAge(){
    //jQuery adds this parameter with a timestamp to indicate it does not want cached results.
    $this->forceRefresh = !empty($_GET["_"]);

    $this->maxAge = new DateInterval( $this->report["max-age"] ?: "P1D" );
    if (!empty($_GET["max-age"])){
      try{
        $override = ctype_digit($_GET["max-age"]) ? "PT" . $_GET["max-age"] . "S" : $_GET["max-age"];
        $this->maxAge = new DateInterval($override);
      }
      catch (Exception $ex){
        //log this, but just keep the default P1D.
        trigger_error("Tried to initialize maxAge and failed: " . $_GET["max-age"], E_USER_WARNING);
      }
    }
  }

  //set paramString
  //paramString is the same pretty URL that fetched the report in the first place
  //it must be unique per requested report; it is the primary key in the cache_state table.
  //paramString could have user-entered data in it; use prepared statements when using it in SQL.
  private function setParamString(){
    $in = $_GET ?: array();
    $params = array();
    if (count($in) > 0){
      ksort($in);
      foreach($in as $key => $val){
        if (is_array($val)){
          $val = array_values($val);
          sort($val);
          $val = join(",", $val);
          $key.= "[]"; //php would have stripped this off the parameter name to make the array of values in $_GET
        }

        //ignore the passed parameters if they are not specified in reports.json.
        if ((isset($this->report["req-params"]) && in_array($key, $this->report["req-params"])) ||
            (isset($this->report["opt-params"]) && in_array($key, $this->report["opt-params"])) ){
          $params[] = $key;
          $params[] = $val;
        }
      }
    }
    //PHP_SELF is stored in a directory like /something/something/category/reportname/report.php
    //we want category and reportname in the fixedParams.
    $allPathParts = explode("/", $_SERVER['PHP_SELF']);
    $fixedParams = join("/", array_splice($allPathParts, -3, 2));
    $this->paramString = "/" . $fixedParams . "/" . join("/", $params) . "/";

    //we'd like to have a descriptive key, but if that's too long then a unique key will be good enough.
    if (strlen($this->paramString) > 255){
      $this->paramString = "/" . $fixedParams . "/" . md5($this->paramString) . "/";
    }
  }

  private function setLastUpdated(){
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
