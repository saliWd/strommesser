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

function validUseridInPost ($dbConn): int {        
  $unsafeUserid = safeIntFromExt('POST', 'userid', 11); // maximum length of 11
  $result = $dbConn->query('SELECT `id` FROM `user` WHERE `id` = '.$unsafeUserid.' LIMIT 1;');
  if ($result->num_rows !== 1) {
    return 0; // invalid userid
  }
  $row = $result->fetch_assoc();
  return (int)$row['id'];
}

function checkHashUserid ($dbConn, int $userid): bool {
  $unsafeRandNum = safeIntFromExt('POST', 'randNum', 8); // range 1 to 10'000 (0 excluded)
  $unsafePostHash = safeHexFromExt('POST', 'hash', 64); 
  if ($unsafeRandNum === 0 or $unsafePostHash === '') {
      return FALSE;
  }
  $result = $dbConn->query('SELECT `post_key` FROM `user` WHERE `id` = "'.$userid.'" LIMIT 1');
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

// function used to check post and get variables 
function checkInputs($dbConn): int {
  if (! verifyGetParams()) { // now I can look the post variables        
    printRawErrorAndDie('Error', 'invalid params');
    return 0;
  }
  $userid = validUseridInPost($dbConn);
  if (! $userid) {
    printRawErrorAndDie('Error', 'userid not supported');
    return 0;
  }
  if (! checkHashUserid($dbConn, $userid)) {
    printRawErrorAndDie('Error', 'access key not ok');
    return 0;
  }
  return $userid;
}

function printNavMenu (string $siteSafe): void {  
  $home   = ($siteSafe === 'index.php') ? '<li class="differentColor">Verbrauch</li>' : '<li><a href="index.php">Verbrauch</a></li>';
  $statistic  = ($siteSafe === 'statistic.php') ? '<li class="differentColor">Statistik</li>' : '<li><a href="statistic.php">Statistik</a></li>';
  $settings  = ($siteSafe === 'settings.php') ? '<li class="differentColor">Einstellungen</li>' : '<li><a href="settings.php">Einstellungen</a></li>';
  // login site not available as list item, will be redirected to login site from various pages
  $logout = '<li><a href="login.php?do=2" id="navLogoutLink">Log out</a></li>'; 
  
  echo '
  <nav style="width:400px">
    <div id="menuToggle">
      <input type="checkbox">
      <span></span>
      <span></span>
      <span></span>
      <ul id="menu">
        <li>&nbsp;</li>
        <li><a href="../">Home</a></li>
        '.$home.'
        '.$statistic.'
        '.$settings.'
        <li>&nbsp;</li>
        '.$logout.'
      </ul>
    </div>
  </nav>';
}

function getDailyValues($dbConn, int $weeksPast, int $userid):string {
  $mWeeks = $weeksPast + 1; // for the current week, I need to search for the last Monday (not this Monday). So one week back

  $minusWeekArr = array($mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks); // 0 to 7
  $weekday = (int)(date_create()->format('N')); // N: 1 (for Monday) through 7 (for Sunday)
  for ($i = $weekday - 1; $i < 8; $i++) { // i = 0 .. 7
    $minusWeekArr[$i] = $minusWeekArr[$i] - 1; // one week less
  }
  $dailyStrings = array( // maybe: could this be done more nicely?
    date_create('-'.$minusWeekArr[0].' week Monday 00:00')->format('Y-m-d 00:00:00'),
    date_create('-'.$minusWeekArr[1].' week Tuesday 00:00')->format('Y-m-d 00:00:00'),
    date_create('-'.$minusWeekArr[2].' week Wednesday 00:00')->format('Y-m-d 00:00:00'),
    date_create('-'.$minusWeekArr[3].' week Thursday 00:00')->format('Y-m-d 00:00:00'), // last week (if today is Friday)
    date_create('-'.$minusWeekArr[4].' week Friday 00:00')->format('Y-m-d 00:00:00'), // this week (if today is Friday)
    date_create('-'.$minusWeekArr[5].' week Saturday 00:00')->format('Y-m-d 00:00:00'),
    date_create('-'.$minusWeekArr[6].' week Sunday 00:00')->format('Y-m-d 00:00:00'),
    date_create('-'.$minusWeekArr[7].' week Monday 00:00')->format('Y-m-d 00:00:00') // have a additional one
  );

  $val_y = '[ ';
  for ($i = 0; $i < 7; $i++) {
    // for some entries, this sql will return the sum of only one line (thin = 24), for others 24 and for the newest ones it returns the sum of lots of entries 
    $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
    $sql = $sql. ' WHERE `userid` = "'.$userid.'" AND `zeit` > "'.$dailyStrings[$i].'" AND `zeit` < "'.$dailyStrings[$i+1].'";';
    $result = $dbConn->query($sql); // returns only one row
    $row = $result->fetch_assoc();
    
    if ($row['sumZeitDiff'] > 0) { // divide by 0 exception
      $watt = max(round($row['sumConsDiff']*3600*1000 / $row['sumZeitDiff']), 10.0); // max(val,10.0) because 0 in log will not be displayed correctly. 10 to save a 'decade' in range
    } else { 
      $watt = ' '; 
    }      
    $val_y .= $watt.', ';
  }
  $val_y = substr($val_y, 0, -2).' ]'; // remove the last two caracters (a comma-space) and add the brackets after
  return $val_y;
}

function printWeeklyGraph (string $val_y, string $chartId):void {
  echo '
  <canvas id="'.$chartId.'" width="600" height="300" class="mb-2"></canvas>
  <script>
  const ctx'.$chartId.' = document.getElementById("'.$chartId.'");
  const labels'.$chartId.' = [ "Mo", "Di", "Mi", "Do", "Fr", "Sa", "So" ];
  const data'.$chartId.' = {
    labels: labels'.$chartId.',
    datasets: [{
      data: '.$val_y.',
      backgroundColor: [      
        "rgba(255, 99, 132, 0.2)",
        "rgba(255, 159, 64, 0.2)",
        "rgba(255, 205, 86, 0.2)",
        "rgba(75, 192, 192, 0.2)",
        "rgba(54, 162, 235, 0.2)",
        "rgba(153, 102, 255, 0.2)",
        "rgba(201, 203, 207, 0.2)"
      ],
      borderColor: [
        "rgb(255, 99, 132)",
        "rgb(255, 159, 64)",
        "rgb(255, 205, 86)",
        "rgb(75, 192, 192)",
        "rgb(54, 162, 235)",
        "rgb(153, 102, 255)",
        "rgb(201, 203, 207)"
      ],
      borderWidth: 1
    }]
  };
  const config'.$chartId.' = {
    type: "bar",
    data: data'.$chartId.',
    options: { plugins : { legend: { display: false } } },
  };
  const '.$chartId.' = new Chart( document.getElementById("'.$chartId.'"), config'.$chartId.' );
  </script>
  <div class="text-sm">Durchschnittlicher Tagesverbrauch in Watt (ein Wert von 1000 Watt entspricht einem Tagesverbrauch von 24 kWh)</div>
  ';
}

function printBeginOfPage(bool $enableReload, string $timerange, string $site, string $title):void {
  require_once('constants.php');
  if (! in_array($site, $VALID_SITES)) {
    return;
  }
  echo '<!DOCTYPE html>
  <html>
  <head>
  <meta charset="utf-8" />
  ';
  $scripts = '';
  if (($site === "index.php") or ($site === 'statistic.php')) {
    $scripts = '<script src="script/chart.min.js"></script>
  <script src="script/moment.min.mine.js"></script>
  <script src="script/chartjs-adapter-moment.mine.js"></script>
  ';
  } 
  
  echo '<title>StromMesser '.$title.'</title>
  ';
  echo '<meta name="description" content="zeigt deinen Energieverbrauch" />  
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="strommesser.css" type="text/css" />
  '.$scripts;  
  if ($enableReload) {
    echo '<meta http-equiv="refresh" content="40; url=https://strommesser.ch/verbrauch/index.php?reload=1'.$timerange.'">
    ';
  }
  echo '</head>
  <body>
  ';
  printNavMenu($site);
  echo '
  <div class="container mx-auto px-4 py-2 lg text-center">
  <h1 class="text-2xl m-2">'.$title.'</h1>
  ';
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

function limitInt (int $input, int $lower, int $upper): int {
  return min(max($input,$lower),$upper);  
}

 