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
    <meta charset="utf-8">
    <title>Error page</title>
    <meta name="description" content="a generic error page">  
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="strommesser.css" type="text/css">';
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

function printMonthly($dbConn, int $userid):void {  
  printMonthlyGraph (values:getMonthlyValues(dbConn:$dbConn, userid:$userid), chartId:'MonthlyNow');
}

function printWeekly($dbConn, int $userid, bool $twoWeeks):void {
  printWeeklyGraph(values:getWeeklyValues(dbConn:$dbConn, weeksPast:0, userid:$userid), chartId:'WeeklyNow', title:'diese');
  if($twoWeeks) {
    printWeeklyGraph(values:getWeeklyValues(dbConn:$dbConn, weeksPast:1, userid:$userid), chartId:'WeeklyLast', title:'letzte');  
  }  
}


function printColors(int $limit):void {
  $COLORS = ['255,99,132','255,159,64','255,205,86','75,192,192','54,162,235','153,102,255','201,203,207'];
  echo "\n      backgroundColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgba('.$COLORS[$i % 7].', 0.2)"';
    if($i != ($limit-1)) { echo ",\n"; }
  }
  echo "],\n      borderColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgb('.$COLORS[$i % 7].')"';
    if($i != ($limit-1)) { echo ",\n"; }
  }
  echo '],';
}

function getSvg(bool $questionMark) {
  if ($questionMark) { // a "?" sign in a circle
    return '<svg class="w-4 h-4 ml-2 text-gray-400 hover:text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path></svg>';
  } else { // a ">" sign (but nicely drawn)
    return '<svg class="w-4 h-4 ml-1" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>';
  }
}

function printPopOverLnk(string $chartId):void {    
  echo '
  <p class="flex items-center text-sm font-light text-gray-500">Info / Details:
    <button data-popover-target="popover-description'.$chartId.'" data-popover-placement="bottom-end" type="button">'.getSvg(questionMark:TRUE).'<span class="sr-only">Info</span></button>
  </p>
  <div data-popover id="popover-description'.$chartId.'" role="tooltip" class="text-left absolute z-10 invisible inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm opacity-0 w-72">
    <div class="p-3 space-y-2">
';
}

function printMonthlyGraph (array $values, string $chartId):void {    
  echo '
  <div class="mt-4 text-xl" id="anchor'.$chartId.'">Tagesverbrauch diesen Monat</div>
  <canvas id="'.$chartId.'" width="600" height="300" class="mb-2"></canvas>
  <script>
  const ctx'.$chartId.' = document.getElementById("'.$chartId.'");
  const labels'.$chartId.' = '.$values[0].';
  const data'.$chartId.' = {
    labels: labels'.$chartId.',
    datasets: [{
      data: '.$values[1].',';
      printColors(limit:$values[2]);
      echo '
      borderWidth: 1
    }]
  };
  const config'.$chartId.' = {
    type: "bar",
    data: data'.$chartId.',
    options: { plugins : { legend: { display: false } } },
  };
  const '.$chartId.' = new Chart( document.getElementById("'.$chartId.'"), config'.$chartId.' );
  </script>';
  printPopOverLnk(chartId:$chartId);
  echo '
        <h3 class="font-semibold text-gray-900">Tagesverbrauch pro Monat</h3>
        <p>Durchschnittsverbrauch in Watt pro Tag. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh. Gemessen wird von 00:00 Uhr bis 23:59 Uhr, bzw. am aktuellen Tag `bis jetzt`</p>
        <h3 class="font-semibold text-gray-900">Mehr Infos</h3>
        <p>Weitere Infos und Verbrauchsstatistiken findest du auf der Statistikseite</p>
        <a href="statistic.php" class="flex items-center font-medium text-blue-600 hover:text-blue-700">Statistik '.getSvg(questionMark:FALSE).'</a>
      </div>
    <div data-popper-arrow></div>
  </div>
  <hr>
  <br>
  ';
}
function printWeeklyGraph (array $values, string $chartId, string $title):void {  
  echo '
  <div class="mt-4 text-xl" id="anchor'.$chartId.'">Tagesverbrauch '.$title.' Woche</div>
  <canvas id="'.$chartId.'" width="600" height="300" class="mb-2"></canvas>
  <script>
  const ctx'.$chartId.' = document.getElementById("'.$chartId.'");
  const labels'.$chartId.' = '.$values[0].';
  const data'.$chartId.' = {
    labels: labels'.$chartId.',
    datasets: [{
      data: '.$values[1].',';
      printColors(limit:7);
      echo '
      borderWidth: 1
    }]
  };
  const config'.$chartId.' = {
    type: "bar",
    data: data'.$chartId.',
    options: { plugins : { legend: { display: false } } },
  };
  const '.$chartId.' = new Chart( document.getElementById("'.$chartId.'"), config'.$chartId.' );
  </script>';
  printPopOverLnk(chartId:$chartId);
  echo '
        <h3 class="font-semibold text-gray-900">Tagesverbrauch</h3>
        <p>Durchschnittsverbrauch in Watt pro Tag. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh. Gemessen wird von 00:00 Uhr bis 23:59 Uhr, bzw. am aktuellen Tag `bis jetzt`</p>
        <h3 class="font-semibold text-gray-900">Mehr Infos</h3>
        <p>Weitere Infos und Verbrauchsstatistiken findest du auf der Statistikseite</p>
        <a href="statistic.php" class="flex items-center font-medium text-blue-600 hover:text-blue-700">Statistik '.getSvg(questionMark:FALSE).'</a>
      </div>
    <div data-popper-arrow></div>
  </div>
  <hr>
  <br>
  ';
}

function getMonthlyValues($dbConn, int $userid):array {
  $yearMonthStr = (date_create()->format('Y-m-'));
  $lastDay = (int)date_create('last day of this month 00:00')->format('d');
  
  $val_y = '[ ';
  $val_x = '[ ';
  for ($i = 1; $i <= $lastDay; $i++) { // 1 to 28 (for February)
    // for some entries, this sql will return the sum of only one line (thin = 24), for others 24 and for the newest ones it returns the sum of lots of entries 
    $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
    $sql = $sql. ' WHERE `userid` = "'.$userid.'" AND `zeit` >= "'.$yearMonthStr.$i.' 00:00:00" AND `zeit` < "'.$yearMonthStr.$i.' 23:59:59";';
    // echo $sql.'<br>';
    $result = $dbConn->query($sql); // returns only one row
    $row = $result->fetch_assoc();
    
    if ($row['sumZeitDiff'] > 0) { // divide by 0 exception
      $watt = max(round($row['sumConsDiff']*3600*1000 / $row['sumZeitDiff']), 10.0); // max(val,10.0) because 0 in log will not be displayed correctly. 10 to save a 'decade' in range
    } else {
      $watt = ' ';
    }
    $val_y .= $watt.', ';
    $val_x .= $i.', ';
  }
  $val_y = substr($val_y, 0, -2).' ]'; // remove the last two caracters (a comma-space) and add the brackets after
  $val_x = substr($val_x, 0, -2).' ]';
  return [$val_x, $val_y, $lastDay];
}

function getWeeklyValues($dbConn, int $weeksPast, int $userid):array {
  $mWeeks = $weeksPast + 1; // for the current week, I need to search for the last Monday (not this Monday). So one week back

  $minusWeekArr = array($mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks,$mWeeks); // 0 to 6
  $weekday = (int)(date_create()->format('N')); // N: 1 (for Monday) through 7 (for Sunday)
  for ($i = $weekday - 1; $i < 7; $i++) { // i = 0 .. 6
    $minusWeekArr[$i] = $minusWeekArr[$i] - 1; // one week less
  }
  $dailyStrings = array( // maybe: could this be done more nicely?
    date_create('-'.$minusWeekArr[0].' week Monday 00:00')->format('Y-m-d '),
    date_create('-'.$minusWeekArr[1].' week Tuesday 00:00')->format('Y-m-d '),
    date_create('-'.$minusWeekArr[2].' week Wednesday 00:00')->format('Y-m-d '),
    date_create('-'.$minusWeekArr[3].' week Thursday 00:00')->format('Y-m-d '), // last week (if today is Friday)
    date_create('-'.$minusWeekArr[4].' week Friday 00:00')->format('Y-m-d '), // this week (if today is Friday)
    date_create('-'.$minusWeekArr[5].' week Saturday 00:00')->format('Y-m-d '),
    date_create('-'.$minusWeekArr[6].' week Sunday 00:00')->format('Y-m-d ')
  );

  $val_y = '[ ';
  for ($i = 0; $i < 7; $i++) {
    // for some entries, this sql will return the sum of only one line (thin = 24), for others 24 and for the newest ones it returns the sum of lots of entries 
    $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
    $sql = $sql. ' WHERE `userid` = "'.$userid.'" AND `zeit` >= "'.$dailyStrings[$i].'00:00:00" AND `zeit` <= "'.$dailyStrings[$i].'23:59:59";';
    // echo $sql.'<br>';
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
  $val_x = '[ "Mo", "Di", "Mi", "Do", "Fr", "Sa", "So" ]'; 
  return [$val_x, $val_y];
}

function printBeginOfPage(bool $enableReload, string $timerange, string $site, string $title):void {
  require_once('constants.php');
  if (! in_array($site, $VALID_SITES)) {
    return;
  }
  echo '<!DOCTYPE html>
  <html>
  <head>
  <meta charset="utf-8">
  ';
  $scripts = '';
  if (($site === "index.php") or ($site === 'statistic.php')) {
    $scripts = '<script src="script/chart.min.js"></script>
  <script src="script/moment.min.mine.js"></script>
  <script src="script/chartjs-adapter-moment.mine.js"></script>
  <script src="script/flowbite.min.js"></script>
  ';
  } 
  
  echo '<title>StromMesser '.$title.'</title>
  ';
  echo '<meta name="description" content="zeigt deinen Energieverbrauch">  
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="strommesser.css" type="text/css">
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

 