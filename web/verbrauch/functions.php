<?php declare(strict_types=1);
// This file is included in other sites

// --------------------------
// class definitions
enum Timerange
{
  case Week;
  case Month;
  case Year;
}
enum Svg
{
  case QuestionMark;
  case ArrowRight;
  case ArrowLeft;
  case ArrowDown;
}
enum Param
{
  case cons;
  case consNt;
  case consHt;
  case consCost;
  case gen;
  case genNt;
  case genHt;
  case genCost;
}

// --------------------------
// function definitions

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
  require_once('../verbrauch/dbConn.php'); // this will return the $dbConn variable as 'new mysqli'
  if ($dbConn->connect_error) {
    printPageAndDie('Connection to the data base failed', 'Please try again later and/or send me an email: web@strommesser.ch');
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
function error (int $errorMsgNum): bool {  // used in login page
  printPageAndDie('Error', 'Fehlernummer: '.$errorMsgNum.'. Probier doch später nochmals oder schreib mir an messer@strommesser.ch');  
  return FALSE; // (not executed). always returning FALSE to simplify coding. Can write "return error(1234);" which will return FALSE.
}

// prints a valid html page and stops php execution
function printPageAndDie (string $heading, string $text): void {
  echo '
  <!DOCTYPE html><html><head>
    <meta charset="utf-8">
    <title>'.$heading.'</title>    
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../verbrauch/strommesser.css" type="text/css">';
  echo '</head><body><div class="row twelve columns textBox"><h4>'.$heading.'</h4><p>'.$text.'</p></div></body></html>';
  die();
}

function printRawErrorAndDie (string $heading, string $text): void {
  echo $heading.': '.$text;
  die();
}  

function validDevice (object $dbConn, string $postIndicator): array {        
  $unsafeDevice = safeStrFromExt('POST', $postIndicator, 8); // maximum length of 8
  $result = $dbConn->query('SELECT `device` FROM `kunden` WHERE 1 ORDER BY `id`;');
  while ($row = $result->fetch_assoc()) {
      if ($unsafeDevice === $row['device']) {
          return array(TRUE, $row['device']);
      }
  }
  return array(FALSE, ''); // valid/deviceString
}

function validUseridInPost (object $dbConn): int {        
  $unsafeUserid = safeIntFromExt('POST', 'userid', 11); // maximum length of 11
  $result = $dbConn->query('SELECT `id` FROM `kunden` WHERE `id` = "'.$unsafeUserid.'" LIMIT 1;');
  if ($result->num_rows !== 1) {
    return 0; // invalid userid
  }
  $row = $result->fetch_assoc();
  return (int)$row['id'];
}

function checkHashUserid (object $dbConn, int $userid): bool {
  $unsafeRandNum = safeIntFromExt('POST', 'randNum', 8); // range 1 to 10'000 (0 excluded)
  $unsafePostHash = safeHexFromExt('POST', 'hash', 64); 
  if ($unsafeRandNum === 0 or $unsafePostHash === '') {
      return FALSE;
  }
  $result = $dbConn->query('SELECT `post_key` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
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
function checkInputs(object $dbConn): int {
  if (! verifyGetParams()) { // now I can look the post variables        
    printRawErrorAndDie('Error', 'invalid params');
    return 0;
  }
  $userid = validUseridInPost(dbConn:$dbConn);
  if (! $userid) {
    printRawErrorAndDie('Error', 'userid not supported');
    return 0;
  }
  if (! checkHashUserid(dbConn:$dbConn, userid:$userid)) {
    printRawErrorAndDie('Error', 'access key not ok');
    return 0;
  }
  return $userid;
}

function printColors(int $limit, int $offset):void {
  $COLORS = ['255,99,132','255,159,64','255,205,86','75,192,192','54,162,235','153,102,255','201,203,207'];
  echo "\n      backgroundColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgba('.$COLORS[($i+$offset) % 7].', 0.2)"';
    if($i != ($limit-1)) { echo ",\n"; }
  }
  echo "],\n      borderColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgb('.$COLORS[($i+$offset) % 7].')"';
    if($i != ($limit-1)) { echo ",\n"; }
  }
  echo '],';
}

function getSvg(Svg $whichSvg, string $classString='w-4 h-4 ml-1'):string {
  return match ($whichSvg) {
    Svg::QuestionMark => '<svg class="'.$classString.'" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path></svg>',
    Svg::ArrowRight   => '<svg class="'.$classString.'" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>',
    Svg::ArrowLeft    => '<svg class="'.$classString.' rotate-180" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg>',
    Svg::ArrowDown    => '<svg class="'.$classString.'" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>'
  };
}

function getHr():string {
  return '
  <div class="inline-flex items-center justify-center w-full">
    <hr class="w-full h-px my-8 bg-gray-200 border-0">
    <div class="absolute px-4 -translate-x-1/2 left-1/2">
      <a href="#anchorTopOfPage"><img src="../verbrauch/img/messer_200.png" class="h-6 mr-3 sm:h-10" alt="StromMesser Logo"></a>
    </div>
  </div>
  ';
}

function printPopOverLnk(string $chartId):void {    
  echo '
  <p class="flex items-center text-sm font-light text-gray-500">Info / Details:
    <button data-popover-target="popover-description'.$chartId.'" data-popover-placement="bottom-end" type="button">'.getSvg(whichSvg:Svg::QuestionMark, classString:'w-4 h-4 ml-2 text-gray-400 hover:text-gray-500').'<span class="sr-only">Info</span></button>
  </p>
  <div data-popover id="popover-description'.$chartId.'" role="tooltip" class="text-left absolute z-10 invisible inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm opacity-0 w-72">
    <div class="p-3 space-y-2">
';
}

function printBarGraph (
  object $dbConn, int $userid, 
  Timerange $timerange, Param $param, 
  int $goBack, bool $isIndexPage=FALSE
):void {
  $now = date_create(); // TODO: some stuff here is repeated in getValues, not nice
  if ($timerange === Timerange::Year) { 
    $year = ((int)$now->format('Y')) - $goBack;
    if ($goBack === 0) { $title = 'dieses Jahr'; }
    elseif ($goBack === 1) { $title = 'letztes Jahr'; }
    else { $title = 'Jahr '.$year; }
    $chartId = 'Y';
  } elseif ($timerange === Timerange::Month) {
    $monNames = array('Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'); // need german naming, not using format('M')
    $month = ((int)$now->format('m')) - $goBack;
    while ($month < 1) {
      $month += 12;
    }
    $title = $monNames[$month-1];
    $chartId = 'M';
  } elseif ($timerange === Timerange::Week) {
    if ($goBack === 0) { $title = 'diese Woche'; }
    elseif ($goBack === 1) { $title = 'letzte Woche'; }
    else {       
      $now->modify('-'.$goBack.' weeks');
      $weekNr = $now->format("W");
      $title = 'Woche '.$weekNr;    
    }
    $chartId = 'W';
  }
  
  $chartId .= $param->name;
  if ($param === Param::cons)         { $paramText = 'Verbrauch'; }
  elseif ($param === Param::consNt)   { $paramText = 'Verbrauch NT'; } 
  elseif ($param === Param::consHt)   { $paramText = 'Verbrauch HT'; } 
  elseif ($param === Param::gen)      { $paramText = 'Einspeisung'; } 
  elseif ($param === Param::genNt)    { $paramText = 'Einspeisung NT'; } 
  elseif ($param === Param::genHt)    { $paramText = 'Einspeisung HT'; } 
  elseif ($param === Param::consCost) { $paramText = 'Kosten'; } 
  elseif ($param === Param::genCost)  { $paramText = 'Verdienst'; } 
  else {$paramText = 'Fehler Enum';}
  
  $values = getValues(dbConn:$dbConn, userid:$userid, timerange:$timerange, param:$param, goBack:$goBack);
  $title .= ' (Ø: '.$values[5].'W)';
  if ($goBack > 0) {
    $forwardLink = '<a class="text-blue-600 hover:text-blue-700 inline-flex" href="?goBack'.$chartId.'='.($goBack-1).'#anchor'.$chartId.'">'.getSvg(whichSvg:Svg::ArrowRight, classString:'w-8 h-8').'</a>';
  } else {
    $forwardLink = '<span class="inline-flex">&nbsp;</span>';
  }
  echo '
  <div class="flex mt-4">
    <div class="grow h-8 scroll-mt-16" id="anchor'.$chartId.'">
      <a class="text-blue-600 hover:text-blue-700 inline-flex" href="?goBack'.$chartId.'='.($goBack+1).'#anchor'.$chartId.'">'.getSvg(whichSvg:Svg::ArrowLeft, classString:'w-8 h-8').'</a>
      <span class="text-xl mx-4 inline-flex h-8 align-middle mb-4">'.$paramText.' '.$title.'</span>
      '.$forwardLink.'
    </div>
  </div>
  <canvas id="'.$chartId.'" width="600" height="300" class="mb-2"></canvas>
  <script>
  const ctx'.$chartId.' = document.getElementById("'.$chartId.'");
  const labels'.$chartId.' = '.$values[0].';
  const data'.$chartId.' = {
    labels: labels'.$chartId.',
    datasets: [{
      data: '.$values[1].',';
      printColors(limit:$values[2], offset:$values[3]);
      echo '
      borderWidth: 1,
      order: 1
    },
    {      
      label: "Durchschnitt",
      data: '.$values[4].',
      borderColor: "rgba(20, 20, 20, 0.8)",
      backgroundColor: "rgb(255,255,255)",
      borderWidth: 2,
      borderDash: [10, 5],
      pointStyle: false,
      type: "line",
      order: 0
    }]
  };
  const config'.$chartId.' = {
    type: "bar",
    data: data'.$chartId.',
    options: { plugins : { legend: { display: false } } },
  };
  const '.$chartId.' = new Chart( document.getElementById("'.$chartId.'"), config'.$chartId.' );
  </script>';
  if ($isIndexPage) {
    printPopOverLnk(chartId:$chartId);
    echo '
        <h3 class="font-semibold text-gray-900">Durchschnittsverbrauch</h3>
        <p>Durchschnittsverbrauch in Watt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh</p>
        <h3 class="font-semibold text-gray-900">Mehr Infos</h3>
        <p>Weitere Infos und Verbrauchsstatistiken findest du auf der Statistikseite</p>
        <a href="../verbrauch/statistic.php" class="flex items-center font-medium text-blue-600 hover:text-blue-700">Statistik '.getSvg(whichSvg:Svg::ArrowRight).'</a>
      </div>
    <div data-popper-arrow></div>
  </div>';
  }
  echo getHr().'
  <br>
  ';
}

function getWattSum(object $dbConn, int $userid, Param $param, string $dayA, string $dayB) { // returns either a number or a string
  if (($param === Param::consCost) or ($param === Param::genCost)) {  // TODO: those two need special treatment
    printPageAndDie('Invalid parameter at graph generation', 'Please try again later and/or send me an email: web@strommesser.ch');    
  }
  $sql = 'SELECT SUM(`'.$param->name.'Diff`) as `sumDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
  $sql .= ' WHERE `userid` = "'.$userid.'" AND `zeit` >= "'.$dayA.' 00:00:00" AND `zeit` <= "'.$dayB.' 23:59:59";';
  $result = $dbConn->query($sql); // returns only one row
  $row = $result->fetch_assoc();
    
  if ($row['sumZeitDiff'] > 0) { // divide by 0 exception
    return round($row['sumDiff']*3600*1000 / $row['sumZeitDiff']);
  } 
  return ' '; // not really nice, returning a string
}

function getValues(
  object $dbConn, int $userid, 
  Timerange $timerange, Param $param, 
  int $goBack
):array {
  $val_x = '[ ';
  $val_y = '[ ';
  $numOfEntries = 0;
  $weekDayOffset = 0;
  $val_y_ave = '[ ';
  $average = 0.0;
  
  $now = date_create();
  $year = (int)$now->format('Y'); // current year
  $month = (int)$now->format('m'); // current month
  $day = (int)$now->format('d'); // current day
  $numOfEntries = 0;
  $weekDayOffset = 0;

  if ($timerange === Timerange::Year) { // generates one value per week
    $monNames = array('Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'); // need german naming, not using format('M')
    $year = $year - $goBack;
    $startDay = date_create($year.'-01-01');
    $weekday = (int)$startDay->format('N') - 1; // 0 (for Monday) through 6 (for Sunday)
    if ($weekday > 0) {
      $startDay->modify('+'.(7-$weekday).' days'); // that gets me first Monday in the year
    }
    $average = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$startDay->format('Y-m-d'), dayB:$year.'-12-31');
    for ($week = 1; $week <= 52; $week++) { // 364 days (one or two days are left over)
      $dayStrA = $startDay->format('Y-m-d');
      $month = (int)$startDay->format('m'); // plot the month of the Monday
      $startDay->modify('+6 days'); // Sunday
      $dayStrB = $startDay->format('Y-m-d');
      $val_y .= getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStrA, dayB:$dayStrB).', ';
      $val_y_ave .= $average.', ';
      $val_x .= '"'.$monNames[$month-1].'", ';
      $numOfEntries++;
      
      $startDay->modify('+1 days'); // Monday again
    }
  } elseif ($timerange === Timerange::Month) { // maybe to do: could switch to date->modify method
    $month = $month - $goBack; // NB: goBack must not be greater than 12
    while ($month < 1) {
      $year--;
      $month += 12;
    }
    $startDay = date_create($year.'-'.$month.'-01');
    $weekDayOffset = (int)$startDay->format('N') - 1; // 0 (for Monday) through 6 (for Sunday). Colors are matching between week and month
    $lastDay = (int)date_create('last day of '.$year.'-'.$month)->format('d');
    $average = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$year.'-'.$month.'-01', dayB:$year.'-'.$month.'-'.$lastDay);
    for ($day = 1; $day <= $lastDay; $day++) { // 1 to 28 (for February)
      $dayStr = $year.'-'.$month.'-'.$day;
      $val_y .= getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStr, dayB:$dayStr).', ';
      $val_y_ave .= $average.', ';
      $val_x .= $day.', ';
      $numOfEntries++;
    }
  } elseif ($timerange === Timerange::Week) {
    $numOfEntries = 7;
    $startDay = $now;
    $startDay->modify('-'.$goBack.' weeks');
    $weekday = (int)$startDay->format('N') - 1; // 0 (for Monday) through 6 (for Sunday)
    $startDay->modify('-'.$weekday.' days'); // that gets me Monday in this week
    $endDay = clone $startDay; // clone is needed here
    $endDay->modify('+6 days');
    $average = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$startDay->format('Y-m-d'), dayB:$endDay->format('Y-m-d'));
    for ($day = 1; $day <= $numOfEntries; $day++) {
      $dayStr = $startDay->format('Y-m-d');
      $val_y .= getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStr, dayB:$dayStr).', ';
      $val_y_ave .= $average.', ';
      $startDay->modify('+1 days');
    }
    $val_x .= '"Mo", "Di", "Mi", "Do", "Fr", "Sa", "So", ';
  }
  
  $val_y = substr($val_y, 0, -2).' ]'; // remove the last two caracters (a comma-space) and add the brackets after
  $val_y_ave = substr($val_y_ave, 0, -2).' ]'; 
  $val_x = substr($val_x, 0, -2).' ]';
  return [$val_x, $val_y, $numOfEntries, $weekDayOffset, $val_y_ave, $average];
}

// prints header with css/js and body, container-div and h1 title
function printBeginOfPage_v2(string $site, string $refreshMeta='', string $title=''):void {
  $SITE_TITLES = array(
    'index.php' => 'Verbrauch',
    'settings.php' => 'Einstellungen',
    'login.php' => 'Login, Logout',
    'statistic.php' => 'Statistiken',
    'status.php' => 'Status',
    'contact.php' => 'Kontaktformular'
  );
  echo '<!DOCTYPE html>
  <html>
  <head>
  <meta charset="utf-8">
  ';
  $scripts = '';
  if (($site === 'index.php') or ($site === 'statistic.php')) {
    $scripts = '<script src="../verbrauch/script/chart.min.js"></script>
  <script src="../verbrauch/script/moment.min.mine.js"></script>
  <script src="../verbrauch/script/chartjs-adapter-moment.mine.js"></script>';
  } 
  
  echo '<title>StromMesser '.$SITE_TITLES[$site].'</title>
  ';
  echo '<meta name="description" content="zeigt deinen Energieverbrauch">  
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../verbrauch/strommesser.css" type="text/css">
  <script src="../verbrauch/script/flowbite.min.js"></script>
  '.$scripts.$refreshMeta.'
  </head>
  <body>
  ';
  printNavMenu_v2(site:$site, title:$title);
  echo '
  <div class="container mx-auto px-4 py-2 lg text-center mt-16 scroll-mt-16" id="anchorTopOfPage">
  ';
  return;
}

function printNavMenu_v2 (string $site, string $title): void {
  $topLevelSites = array( // TODO: partial repetition of SITE_TITLES
    ['index.php', 'Verbrauch'],
    ['statistic.php', 'Statistiken'],
    ['settings.php', 'Einstellungen'],
    ['#', '&nbsp;'],
    ['login.php?do=2', 'LogOut']
  );  
  echo '
<nav class="border-gray-400 rounded bg-gray-50 px-2 sm:px-4 fixed w-full top-0 left-0" aria-label="Breadcrumb">
  <ol class="inline-flex items-center mb-3 sm:mb-0">
    <li>
      <div class="flex items-center">
        <button id="dropdownNavMain" data-dropdown-toggle="dropdown-NavMain" class="inline-flex items-center px-3 py-2 text-sm font-normal text-center text-gray-900 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100">
          <a href="#anchorTopOfPage"><img src="../verbrauch/img/messer_200.png" class="h-6 mr-3 sm:h-10" alt="StromMesser Logo"></a>
          StromMesser'.getSvg(whichSvg:Svg::ArrowDown, classString:'w-5 h-5 ml-1').'
        </button>
        <div id="dropdown-NavMain" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44">
          <ul class="py-2 text-sm text-gray-700" aria-labelledby="dropdownDefault">';
  printListItems($topLevelSites);          
  echo '
          </ul>
        </div> 
      </div>
    </li>';

  $inPageTargets = array();  
  $siteName = '';
  if ($site === 'index.php') {
    $inPageTargets = array(
      ['#myChart', 'Aktueller Verbrauch'],
      ['#anchorW', 'Wöchentlich'],
      ['#anchorM', 'Monatlich']
    );
    $siteName = 'Verbrauch';
  } elseif ($site === 'statistic.php') {
    $inPageTargets = array(
      ['#anchorW', 'Wöchentlich'],
      ['#anchorM', 'Monatlich'],
      ['#anchorY', 'Jährlich']
    );
    $siteName = 'Statistiken';
  } elseif ($site === 'settings.php') {
    $inPageTargets = array(
      ['#anchorMiniDisplay', 'Mini-Display'],
      ['#anchorUserAccount', 'Benutzereinstellungen'],
      ['#anchorDataExport', 'Daten exportieren']
    );
    $siteName = 'Einstellungen';
  } elseif ($site === 'login.php') {    
    $siteName = 'Login';
  }
  if ($title) { $siteName = $title; }
  printInPageNav(inPageTargets:$inPageTargets, siteName:$siteName);
  echo '
  </ol>
</nav>';
}

function printInPageNav(array $inPageTargets, string $siteName): void {
  echo '
  <span class="mx-2 text-gray-400">/</span>
  <li aria-current="page">
    <div class="flex items-center">
      <button id="dropdownNav2nd" data-dropdown-toggle="dropdown-Nav2nd" class="inline-flex items-center px-3 py-2 text-xl font-semibold text-center text-gray-900 rounded-lg hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-gray-100">          
        '.$siteName; if ($inPageTargets) { echo getSvg(whichSvg:Svg::ArrowDown, classString:'w-5 h-5 ml-1'); }
  echo '
      </button>';
  if ($inPageTargets) { 
    echo '
      <div id="dropdown-Nav2nd" class="z-10 hidden bg-white divide-y divide-gray-100 rounded-lg shadow w-44">
        <ul class="py-2 text-sm text-gray-700" aria-labelledby="dropdownDefault">';
        printListItems($inPageTargets);
    echo '
        </ul>
      </div>';
  }
  echo '
    </div>
  </li>
';
}

function printListItems(array $items): void {
  foreach ($items as $item) {
    echo '
        <li>
          <a href="'.$item[0].'" class="block px-4 py-2 hover:bg-gray-100">'.$item[1].'</a>
        </li>';
  }
}

// checks the params retrieved over get and returns TRUE if they are ok
function verifyGetParams():bool {  
  if (safeStrFromExt('GET','TX', 4) !== 'pico') {                
      return FALSE;
  }
  if (safeIntFromExt('GET','TXVER', 1) !== 2) { // don't accept other interface version numbers
      return FALSE;
  }
  return TRUE;
}

// sql sanitation and length limitation
function sqlSafeStrFromPost(object $dbConn, string $varName, int $length):string {
  if (isset($_POST[$varName])) {
     return mysqli_real_escape_string($dbConn, (substr($_POST[$varName], 0, $length))); // length-limited variable           
  } else {
     return '';
  }
}

// returns a 'safe' integer. Return value is 0 if the checks did not work out
function makeSafeInt($unsafe, int $length):int {  
  $unsafe = filter_var(substr($unsafe, 0, $length), FILTER_SANITIZE_NUMBER_INT); // sanitize a length-limited variable
  if (filter_var($unsafe, FILTER_VALIDATE_INT)) { 
    return (int)$unsafe;
  } else { 
    return 0;
  }  
}

// returns a 'safe' string. Not that much to do though for a string
function makeSafeStr($unsafe, int $length):string {
  return (htmlentities(substr($unsafe, 0, $length))); // length-limited variable, HTML encoded
}

// returns a 'safe' character-as-hex value
function makeSafeHex($unsafe, int $length):string {  
  $unsafe = substr($unsafe, 0, $length); // length-limited variable  
  if (ctype_xdigit($unsafe)) {
    return (string)$unsafe;
  } else {
    return '0';
  }
}

// checks whether a get/post/cookie variable exists and makes it safe if it does. If not, returns 0
function safeIntFromExt(string $source, string $varName, int $length):int {
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

function safeHexFromExt(string $source, string $varName, int $length):string {
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

function safeStrFromExt(string $source, string $varName, int $length):string {
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

function limitInt(int $input, int $lower, int $upper):int {
  return min(max($input,$lower),$upper);  
}

 