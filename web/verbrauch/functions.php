<?php declare(strict_types=1);
// This file is included in other sites

// this function is called on every (user related) page on the very start  
// it does the session start and opens connection to the data base. Returns the dbConn variable or a boolean
function initialize () {
  session_start(); // this code must precede any HTML output
  if (!getUserid()) {
    redirectRelative('login.php');    
    die(); // this code is not reached because redirect does an exit but it's anyhow cleaner like this
  }
  
  return get_dbConn();  
}

function get_dbConn() {
  require_once('dbConn.php'); // this will return the $dbConn variable as 'new mysqli'
  if ($dbConn->connect_error) {
    printErrorAndDie('Connection to the data base failed', 'Please try again later and/or send me an email: web@strommesser.ch');
  }
  $dbConn->set_charset('utf8');
  return $dbConn;
}

// returns the userid integer from the session variable. userid 1 is special (the demo account)
function getUserid (): int {
  if (isset($_SESSION)) {
	  if (isset($_SESSION['userid'])) {
    	return (int)$_SESSION['userid'];
	  }
  }
  return 0;  // rather return 0 (means userid is not valid) than FALSE
}

// does a (relative) redirect
function redirectRelative (string $page): void {
  // redirecting relative to current page NB: some clients require absolute paths
  $host  = $_SERVER['HTTP_HOST'];
  $uri   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');  
  header('Location: https://'.$host.htmlentities($uri).'/'.$page);
  exit;
}

// displays some very generic failure message
function error (int $errorMsgNum): bool {  
  printErrorAndDie('Error', 'Fehlernummer: '.$errorMsgNum.'. Probier doch später nochmals oder schreib mir an web@strommesser.ch');  
  return FALSE; // (not executed). always returning FALSE to simplify coding. Can write "return error(1234);" which will return FALSE.
}


// prints a valid html error page and stops php execution
function printErrorAndDie (string $heading, string $text): void {
  echo '
  <!DOCTYPE html><html><head>
    <meta charset="utf-8" />
    <title>Error page</title>
    <meta name="description" content="a generic error page" />  
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/verbrauch.css" type="text/css" />';    
  echo '</head><body><div class="row twelve columns textBox"><h4>'.$heading.'</h4><p>'.$text.'</p></div></body></html>';
  die();
}

function printRawErrorAndDie (string $heading, string $text): void {
  echo $heading.': '.$text;
  die();
}  

function validDevice ($dbConn, string $postIndicator): array {        
  $unsafeDevice = safeStrFromExt('POST', $postIndicator, 8); // maximum length of 8
  $result = $dbConn->query('SELECT `device` FROM `user` WHERE 1 ORDER BY `id`;');
  while ($row = $result->fetch_assoc()) {
      if ($unsafeDevice === $row['device']) {
          return array(TRUE, $row['device']);
      }
  }
  return array(FALSE, ''); // valid/deviceString
}

function checkHash ($dbConn, string $device): bool {
  $unsafeRandNum = safeIntFromExt('POST', 'randNum', 8); // range 1 to 10'000 (0 excluded)
  $unsafePostHash = safeHexFromExt('POST', 'hash', 64); 
  if ($unsafeRandNum === 0 or $unsafePostHash === '') {
      return FALSE;
  }
  $result = $dbConn->query('SELECT * FROM `user` WHERE `device` = "'.$device.'" ORDER BY `id` DESC LIMIT 1');
  if ($result->num_rows !== 1) {
      return FALSE;
  }
  $row = $result->fetch_assoc();
  // now do a hash over randNum and the post_key. if that one matches the transmitted hash, we are ok.
  $unsafeRandNum = (string)$unsafeRandNum; // convert the int to a string
  $rxSideHash = hash('sha256',$unsafeRandNum.$row['post_key']);
  if ($rxSideHash === $unsafePostHash) {
      return TRUE;
  } else {
      return FALSE;
  }
}

function printNavMenu (string $siteSafe): void {  
  $wpHome = '<li><a href="../">Home</a></li>'; // I don't display this menu on the wp site
  $home   = ($siteSafe === 'index.php') ? '<li class="differentColor">Verbrauch</li>' : '<li><a href="index.php">Verbrauch</a></li>';
  $links  = ($siteSafe === 'settings.php') ? '<li class="differentColor">Einstellungen</li>' : '<li><a href="settings.php">Einstellungen</a></li>';  
  // login site not available as list item, will be redirected to login site from various pages
  $logout = '<li><a href="login.php?do=2">Log out</a></li>'; 
  
  echo '
  <nav style="width:400px">
    <div id="menuToggle">
      <input type="checkbox">
      <span></span>
      <span></span>
      <span></span>
      <ul id="menu">
        <li>&nbsp;</li>
        '.$wpHome.'
        '.$home.'
        '.$links.'
        <li>&nbsp;</li>
        '.$logout.'
      </ul>
    </div>
  </nav>';
}

function printBeginOfPage(bool $enableReload, string $timerange, string $site, string $logInOut = ''):void {
  require_once('constants.php');
  if (! in_array($site, $VALID_SITES)) {
    return;
  }
  echo '<!DOCTYPE html><html><head><meta charset="utf-8" />';
  
  $title = '';
  $scripts = '';
  if ($site === "index.php") {
    $title = 'Verbrauch';
    $scripts = '<script src="script/chart.min.js"></script>
  <script src="script/moment.min.mine.js"></script>
  <script src="script/chartjs-adapter-moment.mine.js"></script>';
  } elseif ($site === "settings.php") {
    $title = 'Einstellungen';    
  } elseif ($site === 'login.php') {
    $title = $logInOut;
  }
  echo '<title>StromMesser '.$title.'</title>';
  echo '
  <meta name="description" content="zeigt deinen Energieverbrauch" />  
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="strommesser.css" type="text/css" />
  '.$scripts;  
  if ($enableReload) {
    echo '<meta http-equiv="refresh" content="40; url=https://strommesser.ch/verbrauch/index.php?reload=1'.$timerange.'">';
  }
  echo '
  </head><body>';
  printNavMenu($site);
  echo '
  <div class="container mx-auto px-4 py-2 lg text-center">
  <h1 class="text-2xl m-2">'.$title.'</h1>';
  return;
}


// returns the current site in the format 'about.php' in a safe way. Any do=xy parameters are obmitted
function getCurrentSite (): string {  
  $siteUnsafe = substr($_SERVER['SCRIPT_NAME'],11); // SERVER[...] is something like /verbrauch/settings.php (without any parameters)
  require_once('constants.php');

  if (in_array($siteUnsafe, $VALID_SITES)) {
    return $siteUnsafe;
  }
  return ''; 
}

// checks the params retrieved over get and returns TRUE if they are ok
function verifyGetParams (): bool {  
  if (safeStrFromExt('GET','TX', 4) !== 'pico') {                
      return FALSE;
  }
  if (safeIntFromExt('GET','TXVER', 1) !== 2) { // don't accept other interface version numbers
      return FALSE;
  }
  return TRUE;
}

// sql sanitation and length limitation
function sqlSafeStrFromPost ($dbConn, string $varName, int $length): string {
  if (isset($_POST[$varName])) {
     return mysqli_real_escape_string($dbConn, (substr($_POST[$varName], 0, $length))); // length-limited variable           
  } else {
     return '';
  }
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
  // 24h-old: thin with a rate of 1 entry per 15 minutes (about a 2/15 rate)
  // 72h-old: thin with a rate of 1 entry per 4 hours (about a 2/240 rate), resulting in 6 entries per day  
  if (($timeRangeMins !== 15) and ($timeRangeMins !== 240)) { // $timeRangeMins is either 15 or 240
    return;
  }

  $sqlWhereDeviceThin = '`device` = "'.$device.'" AND `thin` < "15"';
  if ($timeRangeMins === 240) {
    $sqlWhereDeviceThin = '`device` = "'.$device.'" AND `thin` < "240"';
  }
  $sql = 'SELECT `zeit` FROM `verbrauch` WHERE '.$sqlWhereDeviceThin.' ORDER BY `id` ASC LIMIT 1;';
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
  $sql = 'SELECT `id` FROM `verbrauch` WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'" ORDER BY `id` ASC LIMIT 240;';
  $result = $dbConn->query($sql);
  if ($result->num_rows < 7) { // otherwise I can't really compact stuff
    // I have an issue when there are gaps in the entries. I then have less than 7 entries per 15 minutes
    $zeitThinStartWithMargin = $zeitThinStart;
    $zeitThinStartWithMargin->modify('- 2 hours');
    if ($zeitToThin < $zeitThinStartWithMargin) {
      // proceed normally
      if($talkative) { echo '...prozessiere '.$result->num_rows.' Einträge (weniger als 7 aber schon alt) seit '.$zeitToThinString; }
    } else {
      if($talkative) { echo 'nur '.$result->num_rows.' Einträge (weniger als 7) seit '.$zeitToThinString; }
      return;
    }
  }
  $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
  $idToUpdate = $row['id'];
  
  $sql = 'SELECT SUM(`aveConsDiff`) as `sumAveConsDiff`, SUM(`aveZeitDiff`) as `sumAveZeitDiff` FROM `verbrauch`';
  $sql = $sql. ' WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'";';
  $result = $dbConn->query($sql);
  $row = $result->fetch_assoc(); 

  // now do the update and then delete the others. Number 15 means: a ratio of about one data item per 15min was implemented 
  $sql = 'UPDATE `verbrauch` SET `aveConsDiff` = "'.$row['sumAveConsDiff'].'", `aveZeitDiff` = "'.$row['sumAveZeitDiff'].'", `thin` = "'.$timeRangeMins.'" WHERE `id` = "'.$idToUpdate.'";';
  $result = $dbConn->query($sql);
  
  $sql = 'DELETE FROM `verbrauch` WHERE '.$sqlWhereDeviceThin.' AND `zeit` < "'.$zeitToThinString.'";';
  $result = $dbConn->query($sql);
  echo $dbConn->affected_rows.' entries have been deleted';
}      
 