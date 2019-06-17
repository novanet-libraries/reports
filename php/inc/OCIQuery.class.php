<?php
//
//A wrapper around some common OCI functions to apply the Iterator interface to query results.
//Constructing an OCIQuery object:
//    -creates a prepared statement
//    -binds its parameters (optional)
//    -executes the statement
//    -returns an Iterable thing that represents returned rows.
//
// So, you would do something like:
//    try{
//        $db   = oci_connect(/*...*/);
//        $sql  = "SELECT NAME FROM TABLE WHERE COUNTRY = :COUNTRY AND PROVINCE = :PROVINCE";
//        $bind = array(":COUNTRY" => "Canada", ":PROVINCE" => "Nova Scotia");
//        $stmt = new OCIQuery($db, $sql, $bind);
//        foreach($stmt as $row){
//            echo $row["NAME"], "\n";
//        }
//        //re-execute with different parameters
//        $bind[":PROVINCE"] = "New Brunswick";
//        $stmt->execute($bind);
//        foreach($stmt as $row){
//            echo $row["NAME"], "\n";
//        }
//      }catch(Exception $ex){
//          /* ... */
//      }
//

class OCIQuery implements Iterator {
  private $parsedStmt;
  private $position;
  private $current;
  private $mode;
  
  private function fetchRow(){
    $this->current = oci_fetch_array($this->parsedStmt, OCI_ASSOC | OCI_RETURN_NULLS | OCI_RETURN_LOBS);
  }

  public function __construct($connection, $sql, $parameters = null, $mode = OCI_COMMIT_ON_SUCCESS, $autoExec = true){
    if ($mode != OCI_COMMIT_ON_SUCCESS && $mode != OCI_NO_AUTO_COMMIT){
      throw new Exception("Did not recoginize mode parameter.");
    }
    $this->mode = $mode;
    
    $this->parsedStmt = oci_parse($connection, $sql);
    if (!$this->parsedStmt){
      $e = oci_error($connection);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }

    if ($autoExec){
      $this->execute($parameters);
    }
  }

  public function execute($parameters = null, $mode = null){
    if (!empty($parameters)){
      foreach($parameters as $name => $value){
        $success = oci_bind_by_name($this->parsedStmt, $name, $parameters[$name]);
        if (!$success){
          $e = oci_error($this->parsedStmt);
          throw new Exception($e['message'] . "\n" . $e['sqltext']);
        }
      }
    }

    $success = oci_execute($this->parsedStmt, ($mode?$mode:$this->mode));
    if (!$success){
      $e = oci_error($this->parsedStmt);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }

    $this->position = 0;
    $this->fetchRow();
  }
  
  //executes query and returns single result rather than iterable result set
  public function result($parameters = null, $mode = null){
    $this->execute($parameters, $mode);
    $cache = array();
    foreach($this as $r){
      $cache[] = $r;
    }
    if (empty($cache)){
      return null;
    }
    else if (count($cache) > 1){
      return $cache;
    }
    else if (count($cache[0]) > 1){
      return $cache[0];
    }
    else {
      return array_pop($cache[0]);
    }
  }
  
  //Iterator Interface
  public function rewind(){
    if ($this->position != 0){
      throw new Exception("Iterator is one way only.  Re-execute the query to start over.");
    }
  }
  public function next(){
    $this->position++;
    $this->fetchRow();
  }
  public function valid(){
    return $this->current !== false;
  }
  public function current(){
    return $this->current;
  }
  public function key(){
    return $this->position;
  }
}
