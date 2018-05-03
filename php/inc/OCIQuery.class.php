<?php
//
//A wrapper around some common OCI functions to apply the Iterator interface.
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
//      }catch(Exception $ex){
//          /* ... */
//      }
//

class OCIQuery implements Iterator {
  private $parsedStmt;
  private $position;
  private $current;

  private function fetchRow(){
    $this->current = oci_fetch_array($this->parsedStmt, OCI_ASSOC | OCI_RETURN_NULLS | OCI_RETURN_LOBS);
  }

  public function __construct($connection, $sql, $bindArgs = null){
    $this->parsedStmt = oci_parse($connection, $sql);
    if (!$this->parsedStmt){
      $e = oci_error($connection);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }

    if (!empty($bindArgs)){
      foreach($bindArgs as $name => $value){
        $success = oci_bind_by_name($this->parsedStmt, $name, $bindArgs[$name]);
        if (!$success){
          $e = oci_error($this->parsedStmt);
          throw new Exception($e['message'] . "\n" . $e['sqltext']);
        }
      }
    }

    $success = oci_execute($this->parsedStmt);
    if (!$success){
      $e = oci_error($this->parsedStmt);
      throw new Exception($e['message'] . "\n" . $e['sqltext']);
    }

    $this->position = 0;
    $this->fetchRow();
  }

  //Iterator Interface
  public function rewind(){
    if ($this->position != 0){
      throw new Exception("Iterator is one way only.  Re-run the query to start over.");
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
