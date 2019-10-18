<?php

/*
** class CallNumber  --  A class representing a Library of Congress call number, and that provides proper
**                       sorting functionality.
**
** A CallNumber object contains an array called 'parts' that stores the various parts of the call number:
   parts {
     subjectLetters    // A - ZZZ
     subjectNumbers    // 1 - 99999.99999999
     dCutter           //date or regiment (i.e. 1900 or 187- or 4TH)
     fCutterLetter     // A - Z
     fCutterNumber     // 1 - 99999
     sCutterLetter     // A - Z
     sCutterDigits     // 1 - 99999
     extraneous        // no formatting rules are applied here.
                       // extraneous can be an empty string, or it could be versioning, year of publication, etc, etc.
  }

** You may also refer to composite parts like so:
   parts {
    subject   // is equivalent to: subjectLetters.' '.subjectNumbers
    fCutter   // is equivalent to: fCutterLetter.fCutterDigits
    sCutter   // is equivalent to: sCutterLetter.sCutterDigits
** }
**
** Implements the Comparable interface for proper sorting functionality.
** Pass CallNumber::compare to Array::usort to sort an array of CallNumbers.
** Use a CallNumber object in a string context to get a normalized call number.
*/

class CallNumber /*implements Comparable*/{
  public $parts = array();
  public $inputString = '';
  private static $pattern = "/^
                             ([A-Za-z]{1,3})\\s{0,3}([1-9]\\d{0,4}(?:\\s{0,3}\\.\\d{1,8})?)
                             (?:
                              \\s{1,3}([1-9][\\d\\-]{0,3}(?:[a-z]|st|ST|nd|ND|rd|RD|th|TH)?)\\s{0,3}\\.?\\s{0,3}([A-Za-z])\\s{0,3}([1-9]\\d{0,5})
                              |\\s{0,3}\\.?\\s{0,3}([A-Za-z])\\s{0,3}([1-9]\\d{0,5})
                              |\\s{1,3}([1-9][\\d\\-]{0,3}(?:[a-z]|st|ST|nd|ND|rd|RD|th|TH)?)
                             )
                             (?:\\s{0,3}([A-Za-z])\\s{0,3}([1-9]\\d{0,5}))?
                             (?:\\s{1,3}(.*))?
                             \$/x";

  //an "english" translation of the regex above:
  //subjectLetters followed by subjectNumbers
  //followed by either: a date Cutter and a Cutter
  //                or: just a Cutter
  //                or: just a date Cutter
  //followed by an optional Cutter
  //followed by an optional 'anything'
  //with limited whitespace allowed pretty much anywhere, and a few required whitespaces.
  //
  //subjectLetters is a sequence of any three letters
  //subject numbers is a number from 1 - 99999.99999999
  //a date Cutter (slightly misnamed) is either a date or a regiment (e.g. 1900 or 196- or 14TH)
  //a Cutter is a letter followed by a number from 1 - 99999

  public function __construct($cnString){
    $this->inputString = $cnString;

    $matches = array();
    preg_match(self::$pattern, $cnString, $matches);
    if (empty($matches) ||
        (!($matches[1] && $matches[2] &&
         (($matches[4] && $matches[5]) || ($matches[6] && $matches[7]) || $matches[3] || $matches[8])))){
         throw new Exception("'$cnString' is invalid input for CallNumber::__construct()");
    }

    $this->parts['subjectLetters']  = empty($matches[1])  ? "" : strtoupper($matches[1]);
    $this->parts['subjectNumbers']  = empty($matches[2])  ? "" : preg_replace("/\\s+/","", $matches[2]);
    $this->parts['dCutter']         = empty($matches[3])  ? (empty($matches[8]) ? "" : $matches[8]) : $matches[3];
    $this->parts['fCutterLetter']   = empty($matches[4])  ? strtoupper($matches[6]) : strtoupper($matches[4]);
    $this->parts['fCutterDigits']   = empty($matches[5])  ? $matches[7] : $matches[5];
    $this->parts['sCutterLetter']   = empty($matches[9])  ? "" : strtoupper($matches[9]);
    $this->parts['sCutterDigits']   = empty($matches[10]) ? "" : $matches[10];
    $this->parts['extraneous']      = empty($matches[11]) ? "" : $matches[11];

    //create 'composite' parts:
    $this->parts['subject'] = $this->parts['subjectLetters'].$this->parts['subjectNumbers'];
    $this->parts['fCutter'] = $this->parts['fCutterLetter'].$this->parts['fCutterDigits'];
    $this->parts['sCutter'] = $this->parts['sCutterLetter'].$this->parts['sCutterDigits'];
  }

//function compare() is provided to offer proper sorting functionality
//by comparing each member of the parts array in turn, each by their own rules.
//The word 'self' in the function arguments is type-hinting.
//The PHP engine with throw a CATCHABLE_FATAL_ERROR if variables that are not CallNumber objects are passed to this function.
  public static function compare(self $a, self $b){
    $c = strcmp($a->parts['subjectLetters'], $b->parts['subjectLetters']);
    if ($c) return $c;

    $c = $a->parts['subjectNumbers'] - $b->parts['subjectNumbers'];
    if ($c) return ($c < 0 ? -1 : 1);

    if ($a->parts['dCutter'] && $b->parts['dCutter']){
      $dateA = str_replace('-', '0', $a->parts['dCutter']);
      $dateB = str_replace('-', '0', $b->parts['dCutter']);
      $c = intval($dateA) - intval($dateB);
      if ($c) return ($c < 0 ? -1 : 1);
      $c = intval($a->parts['dCutter']) - intval($b->parts['dCutter']);
      if ($c) return ($c < 0 ? -1 : 1);
    //}
    //There should be no use case where we are comparing dCutters and only one of the inputs has a dCutter.
    //However, that restriction is not actually "coded in" to this class, so we have to account for it here:
    }else if ($a->parts['dCutter']){
      return -1;
    }else if ($b->parts['dCutter']){
      return 1;
    }

    if ($a->parts['fCutterLetter'] && $b->parts['fCutterLetter']){
      $c = strcmp($a->parts['fCutter'], $b->parts['fCutter']);
      if ($c) return $c;
    }else if ($a->parts['fCutterLetter']){
      return $b->parts['extraneous'] ? -1 : 1;
    }else if ($b->parts['fCutterLetter']){
      return $a->parts['extraneous'] ? 1 : -1;
    }

    if ($a->parts['sCutterLetter'] && $b->parts['sCutterLetter']){
      $c = strcmp($a->parts['sCutter'], $b->parts['sCutter']);
      if ($c) return $c;
    }else if ($a->parts['sCutterLetter']){
      return $b->parts['extraneous'] ? -1 : 1;
    }else if ($b->parts['sCutterLetter']){
      return $a->parts['extraneous'] ? 1 : -1;
    }

    return strcmp($a->parts['extraneous'], $b->parts['extraneous']);
  }

  public function compareTo(self $comparer){
    return self::compare($this, $comparer);
  }

  public function __toString(){
    return $this->parts['subject'].
           (!empty($this->parts['dCutter'])?' '.$this->parts['dCutter']:"").
           (!empty($this->parts['fCutter'])?' .'.$this->parts['fCutter']:'').
           (!empty($this->parts['sCutter'])?' '.$this->parts['sCutter']:'').
           (!empty($this->parts['extraneous'])?' '.$this->parts['extraneous']:'');
  }

}

//procedural-style wrapper
function normalize_callnumber($string){
  try{
    return (string)(new CallNumber($string));
  }
  catch(Exception $ex){
    return false;
  }
}
