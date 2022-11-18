<?php declare(strict_types=1);
// This file is a pure function definition file. It is included in other sites

// function list: 
// 20 - initialize ()
// 22 - printErrorAndDie (string $heading, string $text): void

  
// this function is called on every (user related) page on the very start  
// it does the session start and opens connection to the data base. Returns the dbConn variable or a boolean
function initialize () {
  require_once('dbConn.php'); // this will return the $dbConn variable as 'new mysqli'
  if ($dbConn->connect_error) {
    printErrorAndDie('Connection to the data base failed', 'Please try again later and/or send me an email: sali@widmedia.ch');
  }
  $dbConn->set_charset('utf8');
  return $dbConn;
}

// prints a valid html error page and stops php execution
function printErrorAndDie (string $heading, string $text): void {
  echo '
  <!DOCTYPE html><html><head>
    <meta charset="utf-8" />
    <title>Error page</title>
    <meta name="description" content="a generic error page" />  
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/font.css" type="text/css" />    
    <link rel="stylesheet" href="css/skeleton.css" type="text/css" />';    
  echo '</head><body><div class="row twelve columns textBox"><h4>'.$heading.'</h4><p>'.$text.'</p></div></body></html>';
  die();
}

function printNavMenu (string $siteSafe): void {   
  $home   = ($siteSafe === 'index.php') ? '<li class="menuCurrentPage">Home</li>' : '<li><a href="index.php">Home</a></li>';
  $links  = ($siteSafe === 'settings.php') ? '<li class="menuCurrentPage">Settings</li>' : '<li><a href="settings.php">Settings</a></li>';
  
  echo '
  <nav style="width:400px">
    <div id="menuToggle">
      <input type="checkbox">
      <span></span>
      <span></span>
      <span></span>
      <ul id="menu">
        '.$home.'
        '.$links.'
      </ul>
    </div>
  </nav>';
}

// returns the current site in the format 'about.php' in a safe way. Any do=xy parameters are obmitted
function getCurrentSite (): string {  
  $siteUnsafe = substr($_SERVER['SCRIPT_NAME'],7); // SERVER[...] is something like /start/links.php (without any parameters)   
  if (
      ($siteUnsafe === 'index.php') or 
      ($siteUnsafe === 'settings.php')
     ) {
        return $siteUnsafe;
      }
  return ''; 
}

// returns a 'safe' integer. Return value is 0 if the checks did not work out
function makeSafeInt ($unsafe, int $length): int {  
  $unsafe = filter_var(substr($unsafe, 0, $length), FILTER_SANITIZE_NUMBER_INT); // sanitize a length-limited variable
  if (filter_var($unsafe, FILTER_VALIDATE_INT)) { 
    return (int)$unsafe;
  } else { 
    return 0;
  }  
}

// returns a 'safe' string. Not that much to do though for a string
function makeSafeStr ($unsafe, int $length): string {
  return (htmlentities(substr($unsafe, 0, $length))); // length-limited variable, HTML encoded
}

// returns a 'safe' character-as-hex value
function makeSafeHex ($unsafe, int $length): string {  
  $unsafe = substr($unsafe, 0, $length); // length-limited variable  
  if (ctype_xdigit($unsafe)) {
    return (string)$unsafe;
  } else {
    return '0';
  }
}

// checks whether a get/post/cookie variable exists and makes it safe if it does. If not, returns 0
function safeIntFromExt (string $source, string $varName, int $length): int {
  if (($source === 'GET') and (isset($_GET[$varName]))) {
    return makeSafeInt($_GET[$varName], $length);    
  } elseif (($source === 'POST') and (isset($_POST[$varName]))) {
    return makeSafeInt($_POST[$varName], $length);    
  } elseif (($source === 'COOKIE') and (isset($_COOKIE[$varName]))) {
    return makeSafeInt($_COOKIE[$varName], $length);  
  } else {
    return 0;
  }
}

function safeHexFromExt (string $source, string $varName, int $length): string {
  if (($source === 'GET') and (isset($_GET[$varName]))) {
     return makeSafeHex($_GET[$varName], $length);
   } elseif (($source === 'POST') and (isset($_POST[$varName]))) {
     return makeSafeHex($_POST[$varName], $length);
   } elseif (($source === 'COOKIE') and (isset($_COOKIE[$varName]))) {
     return makeSafeHex($_COOKIE[$varName], $length);
   } else {
     return '0';
   }
}

function safeStrFromExt (string $source, string $varName, int $length): string {
  if (($source === 'GET') and (isset($_GET[$varName]))) {
     return makeSafeStr($_GET[$varName], $length);
   } elseif (($source === 'POST') and (isset($_POST[$varName]))) {
     return makeSafeStr($_POST[$varName], $length);
   } elseif (($source === 'COOKIE') and (isset($_COOKIE[$varName]))) {
     return makeSafeStr($_COOKIE[$varName], $length);
   } else {
     return '';
   }
}

function doDbThinning($dbConn, string $device, bool $talkative, int $timeRangeMins):void {
  // 24h-old: thin with a rate of 1 entry per 15 minutes (about a 1/15 rate)
  // 72h-old: thin with a rate of 1 entry per 4 hours (about a 1/240 rate), resulting in 6 entries per day  
  if (($timeRangeMins !== 15) and ($timeRangeMins !== 240)) { // $timeRangeMins is either 15 or 240
    return;
  }

  $sqlWhereDeviceThin = '`device` = "'.$device.'" AND `thin` < "15"';
  if ($timeRangeMins === 240) {
    $sqlWhereDeviceThin = '`device` = "'.$device.'" AND `thin` < "240"';
  }
  $sql = 'SELECT `zeit` FROM `wmeter` WHERE '.$sqlWhereDeviceThin.' ORDER BY `id` ASC LIMIT 1;';
  $result = $dbConn->query($sql);
  $row = $result->fetch_assoc();
  // get the time, add 15 minutes  
  $zeitToThin = date_create($row['zeit']);
  $zeitToThin->modify('+ '.$timeRangeMins.' minutes');
  $zeitToThinString = $zeitToThin->format('Y-m-d H:i:s');
  
  $zeitThinStart = date_create("now");
  if ($timeRangeMins === 15) {
    $zeitThinStart->modify('- 24 hours');
  } else {
    $zeitThinStart->modify('- 72 hours');
  }
  
  if ($zeitToThin >= $zeitThinStart) {  // if this time is more then 24h old, proceed. Otherwise stop
    if($talkative) { echo 'keine Einträge, die genügend alt sind'; }
    return;
  }
  // get the last one where thinning was not yet applied
  $sql = 'SELECT `id` FROM `wmeter` WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'" ORDER BY `id` ASC LIMIT 240;';
  $result = $dbConn->query($sql);
  if ($result->num_rows < 14) { // otherwise I can't really compact stuff
    // I have an issue when there are gaps in the entries. I then have less than 14 entries per 15 minutes
    $zeitThinStartWithMargin = $zeitThinStart;
    $zeitThinStartWithMargin->modify('- 2 hours');
    if ($zeitToThin < $zeitThinStartWithMargin) {
      // proceed normally
      if($talkative) { echo '...prozessiere '.$result->num_rows.' Einträge (weniger als 14 aber schon alt) seit '.$zeitToThinString; }
    } else {
      if($talkative) { echo 'nur '.$result->num_rows.' Einträge (weniger als 14) seit '.$zeitToThinString; }
      return;
    }
  }
  $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
  $idToUpdate = $row['id'];
  
  $sql = 'SELECT SUM(`aveConsDiff`) as `sumAveConsDiff`, SUM(`aveZeitDiff`) as `sumAveZeitDiff` FROM `wmeter`';
  $sql = $sql. ' WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'";';
  $result = $dbConn->query($sql);
  $row = $result->fetch_assoc(); 

  // now do the update and then delete the others. Number 15 means: a ratio of about 1/15 was implemented 
  $sql = 'UPDATE `wmeter` SET `aveConsDiff` = "'.$row['sumAveConsDiff'].'", `aveZeitDiff` = "'.$row['sumAveZeitDiff'].'", `thin` = "'.$timeRangeMins.'" WHERE `id` = "'.$idToUpdate.'";';
  $result = $dbConn->query($sql);
  
  $sql = 'DELETE FROM `wmeter` WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'";';
  $result = $dbConn->query($sql);
  echo $dbConn->affected_rows.' entries have been deleted';
}      
 