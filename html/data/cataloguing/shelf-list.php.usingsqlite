<?php

require('AlephData.class.php');

header('Content-type: application/json; charset=utf-8');

//validate input
$sublibrary  = null;
$collections = array();
$inputErrors = array();
$prohibited  = '/^AUOLB|AUOLS|.+NET|.+RES|NOVA|WWW$/';
$validSublibraries = AlephData::sublibraries();
$validCollections  = AlephData::collections();
if (empty($validSublibraries) || empty($validCollections)){
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(array('error' => 'Fetching init data failed.'));
  die();
}

$validCodes = array_keys($validSublibraries);
$input = strtoupper($_GET['sublibrary']);
if (in_array($input, $validCodes)){
  $sublibrary = $input;
}
else{
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(
    array(
      'error' => 'Invalid sublibrary',
      'valid' => $validCodes
    )
  );
  die();
}

$validCodes = array_keys($validCollections[$sublibrary]);
$input = $_GET['collection'];
foreach($input as $code){
  $code = strtoupper($code);
  if (!in_array($code, $validCodes)){
    $inputErrors[] = "collection code does not exist in '$sublibrary'";
  }
  else if (preg_match($prohibited, $code)){
    $inputErrors[] = "collection code is prohibited from this report";
  }
  else{
    $collections[] = $code;
  }
}

if (count($collections) > 5){
  $inputErrors[] = "too many collection codes";
  $collections = array();
}

if (empty($collections)){
  header('HTTP/1.1 400 Bad Request');
  echo json_encode(
    array(
      'error'      => 'Invalid collection',
      'errors'     => $inputErrors,
      'valid'      => $validCodes,
      'prohibited' => $prohibited
    )
  );
  die();
}

sort($collections);

try {
  
  $tmpFile = rtrim(sys_get_temp_dir(), "/") .
             "/shelflist-" . join("-", $collections) . ".sqlite3";

  $sqlite = connectToTempDB($tmpFile);
  if (!tempDBisPopulated($sqlite) || !tempDBisRecent($tmpFile)){
    populateTempDB($sqlite, $sublibrary, $collections);
  }

  //fetch data and print it as JSON
  //(but don't let json_encode build the whole damn thing in memory)
  try{
    $date = date('Y-m-d H:i:s', filemtime($tmpFile));
    echo '{"date":', json_encode($date), ',"data":';
    //extract items from temp database in LC order.
    $result = $sqlite->query("SELECT * FROM items ORDER BY callnumber, title, description");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row){
      echo '[', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      while ($row = $result->fetchArray(SQLITE3_ASSOC)){
        echo ',', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
      }
      echo ']';
    }
    else{
      echo '[]'; //no data.
    }
    echo '}';
  }
  catch (Exception $ex){
    throw new Exception("Error during output");
  }

}
catch (Throwable $err){
  if (file_exists($tmpFile)){
    //if we made a cached file, but encountered an error, the cached file probably
    //includes the error and/or prevents us from fixing it.
    unlink($tmpFile);
  }
  header('HTTP/1.1 500 Internal Server Error');
  echo json_encode(['error' => $err->getMessage(), 'trace' => $err->getTraceAsString() ]);
  die();
}





function connectToTempDB($filename){
  // set up SQLite3 temporary database
  try{
    $sqlite = new SQLite3($filename);
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
    if (!$sqlite->loadExtension('sqlite3_lccn_ext.so')){
      throw new Exception("Error loading extension: " . $sqlite->lastErrorMsg());
    }

    return $sqlite;
  }
  catch (Exception $ex){
    throw new Exception("Error creating temp database");
  }
}      
function tempDBisPopulated($sqlite){  
  $result = $sqlite->query("select count(*) from sqlite_master where type='table'");
  $row = $result->fetchArray(SQLITE3_NUM);
  $count = $row[0];
  return $count == 1;
}
function tempDBisRecent($filename){
  try{
    $now     = new DateTime();
    $modTime = new DateTime("@" . filemtime($filename));
    $maxAge  = new DateInterval('P1M');
    if (!empty($_GET['max-age'])){
      try{
        $maxAge = new DateInterval($_GET['max-age']);
      }
      catch (Exception $ex){}
    }
    
    return $modTime > $now->sub($maxAge);
  }
  catch (Exception $ex){
    return false;
  }
}

function populateTempDB($sqlite, $sublibrary, $collections){
  //set up Oracle connection
  try{
    require 'AlephOracle.class.php';
    $aleph = new AlephOracle(AlephOracle::TEST);
  }
  catch (Exception $ex){
    throw new Exception("Error connecting to Aleph database");
  }

  //transfer Oracle data into SQLite
  try{
    $selectStmt = file_get_contents('./shelf-list.sql');
    $bind = array(':SUBLIB' => $sublibrary);
    $collectionParam = "('" . join("','", $collections) . "')";
    $selectStmt = str_replace(':COLLECTIONS', $collectionParam, $selectStmt);

    $sqlite->exec('DROP TABLE IF EXISTS ITEMS');
    //ignore any error in that statement.
    $sqlite->exec('CREATE TABLE ITEMS(
      TITLE, AUTHOR, ISN, BIB_NUMBER, CALLNUMBER TEXT COLLATE LCCN, DESCRIPTION,
      COLLECTION, BARCODE, ITEM_STATUS, PROCESS_STATUS, DUEDATE, LAST_EDIT)'
    );
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
    
    $insertStmt = $sqlite->prepare("INSERT INTO ITEMS VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
    $sqlite->exec('BEGIN TRANSACTION');
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
    foreach($aleph->query($selectStmt, $bind) as $row){
      $insertStmt->bindValue(1,  $row['TITLE'],          SQLITE3_TEXT);
      $insertStmt->bindValue(2,  $row['AUTHOR'],         SQLITE3_TEXT);
      $insertStmt->bindValue(3,  $row['ISN'],            SQLITE3_TEXT);
      $insertStmt->bindValue(4,  $row['BIB_NUMBER'],     SQLITE3_TEXT);
      $insertStmt->bindValue(5,  $row['CALLNUMBER'],     SQLITE3_TEXT);
      $insertStmt->bindValue(6,  $row['DESCRIPTION'],    SQLITE3_TEXT);
      $insertStmt->bindValue(7,  $row['COLLECTION'],     SQLITE3_TEXT);
      $insertStmt->bindValue(8,  $row['BARCODE'],        SQLITE3_TEXT);
      $insertStmt->bindValue(9,  $row['ITEM_STATUS'],    SQLITE3_TEXT);
      $insertStmt->bindValue(10, $row['PROCESS_STATUS'], SQLITE3_TEXT);
      $insertStmt->bindValue(11, $row['DUEDATE'],        SQLITE3_TEXT);
      $insertStmt->bindValue(12, $row['LAST_EDIT'],      SQLITE3_TEXT);
      $insertStmt->execute();
      if ($sqlite->lastErrorCode() != 0){
        throw new Exception($sqlite->lastErrorMsg());
      }
    }
    $sqlite->exec('COMMIT');
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
    $sqlite->exec('CREATE INDEX IDX1 ON ITEMS (CALLNUMBER, TITLE, DESCRIPTION);');
    if ($sqlite->lastErrorCode() != 0){
      throw new Exception($sqlite->lastErrorMsg());
    }
  }
  catch (Exception $ex){
    throw new Exception("Error populating temp database");
  }
}
