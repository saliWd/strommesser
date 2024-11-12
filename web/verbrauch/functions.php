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
  case cost;
  case gen;
  case genNt;
  case genHt;
}

// --------------------------
// function definitions

// this function is called on every (user related) page on the very start  
// it does the session start and opens connection to the data base. Returns the dbConn variable or a boolean
function initialize (): mysqli {
  session_start(); // this code must precede any HTML output
  if (!getUserid()) {
    redirectRelative('login.php');
    die(); // this code is not reached because redirect does an exit but it's anyhow cleaner like this
  }
  
  return get_dbConn();  
}

function get_dbConn(): mysqli {
  require_once('../verbrauch/dbConn.php'); // this sets the User and the PW. It's checked in but only encrypted
  $dbConn = new mysqli("localhost", $dbConnUser, $dbConnPw, "db_sm_verbrauch", 3306); // Create connection
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

// prints color according to the weekday
function printColors(int $limit, int $offset, string $borderCol):void {
  $COLORS = ['255,99,132','255,159,64','255,205,86','75,192,192','54,162,235','153,102,255','201,203,207'];
  echo "\n      backgroundColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgba('.$COLORS[($i+$offset) % 7].', 0.2)"';
    if($i != ($limit-1)) { echo ",\n"; }
  }
  echo "],\n      borderColor: [\n";
  for($i = 0; $i < $limit; $i++) {
    echo '      "rgba('.$borderCol.' 0.4)"';    
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

// prints legend and explanation for all the different graphs. Displayed graphs differ partly between index page and statistics page
function printGraphExplanation(bool $isIndexPage):void {
  // https://flowbite.com/docs/components/tables/#table-with-products
  echo '
  <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
  <table class="w-full text-sm text-left rtl:text-right text-gray-500">
    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
        <tr>
            <th scope="col" class="px-16 py-3"><span class="sr-only">Beispiel</span></th>
            <th scope="col" class="px-6 py-3">Erklärung</th>
        </tr>
    </thead>
    <tbody>
        <tr class="bg-white border-b hover:bg-gray-50">
            <td class="p-4">
                <img src="img/expl_00.png" class="w-16 md:w-32 max-w-full max-h-full" alt="Ausgelesene Einzelwerte">
            </td>
            <td class="px-6 py-4 font-semibold text-gray-900">
                <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900 text-left">Ausgelesene Einzelwerte</h3>
                <p class="mb-3 font-normal text-gray-700 text-left">
                  Alle zwei Minuten wird der Energiezähler ausgelesen. Dies erfolgt mit einer Genauigkeit von 1 Wh (3600 W) über einen Zeitraum von ca. zwei Minuten (120 Sekunden). Für die einzelne Messung entspricht das einer Auflösung von ca. 30 W. Es wird sowohl der Verbrauch als auch die Einspeisung ausgelesen, für den Verbrauch aufgesplittet auf Niedertarif (NT) und Hochtarif (HT).
                </p>
            </td>            
        </tr>
        <tr class="bg-white border-b hover:bg-gray-50">
            <td class="p-4">
                <img src="img/expl_01.png" class="w-16 md:w-32 max-w-full max-h-full" alt="Leistungsmessungen und Kostenmessungen">
            </td>
            <td class="px-6 py-4 font-semibold text-gray-900">
                <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900 text-left">Leistungsmessungen und Kostenmessungen</h3>
                <p class="mb-3 font-normal text-gray-700 text-left">
                  Bei Leistungsmessungen wird jeweils der Durchschnittsverbrauch/Einspeisung angezeigt. Ein Verbrauch von z.B. 1000 Watt entspricht dann einem Tagesverbrauch von 24 kWh.<br>
                  Bei den Kosten werden hingegen die total aufgelaufenen Kosten angezeigt.
                </p>
            </td>
        </tr>
    </tbody>
</table>
</div>';
  
  // images in general: https://flowbite.com/docs/typography/images/#image-card
  // images with a non-white background work better (in cards but also elsewhere)
  
  // not really: https://flowbite.com/docs/components/accordion/ . Accordion work on the principle of: one-open-at-the-time,rest-hidden. rather want everything visible
  // could also use an image with several clickable points (with explanations to those points). drawback: image has to be big enough on mobile and again only one visible at the time
   
  // trial using this example: https://flowbite.com/docs/components/card/#horizontal-card
  // -> not suited, just for headlines and does not work with lots of text on mobile

}

// displays a bar graph with either two values (generated and consumed watt values) per x-point or one value (cost) per x-point
function printBarGraph (
  object $dbConn, int $userid, 
  Timerange $timerange, Param $param, 
  int $goBack, bool $isIndexPage=FALSE
):void {
  $startDate = date_create();
  $year = (int)$startDate->format('Y'); // current year
  $month = (int)$startDate->format('m'); // current month
  if ($timerange === Timerange::Year) { 
    $year = $year - $goBack;
    $startDate = date_create($year.'-01-01');

    if ($goBack === 0)     { $title = 'dieses Jahr'; }
    elseif ($goBack === 1) { $title = 'letztes Jahr'; }
    else                   { $title = 'Jahr '.$year; }
    $chartId = 'Y';    
  } elseif ($timerange === Timerange::Month) {
    $month = $month - $goBack;
    while ($month < 1) {
      $year--;
      $month += 12;
    }
    $startDate = date_create($year.'-'.$month.'-01');

    $monthNames = array('Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'); // need german naming, not using format('M')
    $title = $monthNames[$month-1];
    $chartId = 'M';
  } elseif ($timerange === Timerange::Week) {    
    $startDate->modify('-'.$goBack.' weeks');
    $weekday = (int)$startDate->format('N') - 1; // 0 (for Monday) through 6 (for Sunday)
    $startDate->modify('-'.$weekday.' days'); // that gets me Monday in this week
  
    if ($goBack === 0)     { $title = 'diese Woche'; }
    elseif ($goBack === 1) { $title = 'letzte Woche'; }
    else {                   $title = 'Woche '.$startDate->format("W");}
    $chartId = 'W';
  }
  
  // returns: [$numOfEntries, $val_x, $val_y_cons, $val_y_gen, $val_y_cons_ave, $val_y_gen_ave, $ave_cons, $ave_gen, $weekDayOffset]
  $values = getValues(dbConn:$dbConn, userid:$userid, timerange:$timerange, param:$param, startDate:$startDate);  

  $statisticLink = 'statistic.php#anchor'.$chartId;
  $chartId .= $param->name;
  $numbersText = ' (Ø: <span class="text-green-600">'.$values[7].'</span>/<span class="text-red-500">'.$values[6].'</span>W)';
  if ($param === Param::cons)       { $paramText = 'Leistung';}
  elseif ($param === Param::consNt) { $paramText = 'Leistung NT';}
  elseif ($param === Param::consHt) { $paramText = 'Leistung HT';}
  elseif ($param === Param::cost)   { 
    if ($values[7] < 0) {
      $paramText = 'Kosten'; 
      $textColor = 'text-red-500'; 
    } else {
      $paramText = 'Ertrag'; 
      $textColor = 'text-green-600';
    }
    $numbersText = ' (<span class="'.$textColor.'">Ø: '.number_format((float)$values[7], 2, '.', '').'</span>)';
  }
  $title .= $numbersText;
  
  if ($goBack > 0) {
    $forwardLink = '<a class="text-blue-600 hover:text-blue-700 inline-flex" href="?goBack'.$chartId.'='.($goBack-1).'#anchor'.$chartId.'">'.getSvg(whichSvg:Svg::ArrowRight, classString:'w-8 h-8').'</a>';
  } else {
    $forwardLink = '<span class="inline-flex">&nbsp;</span>';
  }
  
  echo '
    <div class="flex mt-4">
      <div class="grow h-8 scroll-mt-14" id="anchor'.$chartId.'">
        <a class="text-blue-600 hover:text-blue-700 inline-flex" href="?goBack'.$chartId.'='.($goBack+1).'#anchor'.$chartId.'">'.getSvg(whichSvg:Svg::ArrowLeft, classString:'w-8 h-8').'</a>
        <span class="text-l inline-flex h-8 align-middle mb-4">'.$paramText.' '.$title.'</span>
        '.$forwardLink.'
      </div>
    </div>
    <canvas id="'.$chartId.'" width="600" height="300" class="mb-2"></canvas>
    <script>
    const ctx'.$chartId.' = document.getElementById("'.$chartId.'");
    const labels'.$chartId.' = '.$values[1].';
    const data'.$chartId.' = {
      labels: labels'.$chartId.', ';
  if ($param === Param::cost) {
      $aveColor = '239, 68, 68,'; // red
      if ($values[7] > 0) {$aveColor = '22, 163, 74,';} // green
      echo '
        datasets: [{
        label: "Kosten/Ertrag [CHF]",
        data: '.$values[2].',';
        printColors(limit:$values[0], offset:$values[8], borderCol:'0, 0, 0,');
        echo '
        borderWidth: 2,
        order: 0
      },
      {      
        label: "Durchschnitt [CHF]",
        data: '.$values[5].',
        borderColor: "rgba('.$aveColor.' 0.8)",
        backgroundColor: "rgb(255,255,255)",
        borderWidth: 2,
        borderDash: [10, 5],
        pointStyle: false,
        type: "line",
        order: 2
      }]
    };
    ';    
  } else { // param is not cost     
    echo '
        datasets: [{
        label: "Verbrauch [W]",
        data: '.$values[2].',';
        printColors(limit:$values[0], offset:$values[8], borderCol:'239, 68, 68,');
        echo '
        borderWidth: 2,
        order: 0
      },
      {
        label: "Einspeisung [W]",
        data: '.$values[3].',';
        printColors(limit:$values[0], offset:$values[8], borderCol:'22, 163, 74,');
        echo '
        borderWidth: 2,
        order: 1
      },
          {      
        label: "Durchschnittsverbrauch [W]",
        data: '.$values[4].',
        borderColor: "rgba(239, 68, 68, 0.8)",
        backgroundColor: "rgb(255,255,255)",
        borderWidth: 2,
        borderDash: [10, 5],
        pointStyle: false,
        type: "line",
        order: 2
      },
      {
        label: "Durchschnitt Einspeisung [W]",      
        data: '.$values[5].',
        borderColor: "rgba(22, 163, 74, 0.8)",
        backgroundColor: "rgb(255,255,255)",
        borderWidth: 2,
        borderDash: [10, 5],
        pointStyle: false,
        type: "line",
        order: 3
      }]
    };
    ';
  } // end of param == cost or not
  echo '
  const config'.$chartId.' = {
    type: "bar",
    data: data'.$chartId.',
    options: { plugins : { legend: { display: false } }, scales: { x: { stacked: true, }  }  },
  };
  const '.$chartId.' = new Chart( document.getElementById("'.$chartId.'"), config'.$chartId.' );
  </script>';  
  printPopOverLnk(chartId:$chartId);
  // TODO: different text for cost
  echo '
      <h3 class="font-semibold text-gray-900">Leistung in Watt</h3>
      <p>Verbrauch (rot umrandete Balken) und Einspeisung (grün umrandete Balken) in Watt.<br>
      Gestrichelt dargestellt werden der Durchschnittsverbrauch bzw. die durschnittliche Einspeisung.</p>
      <p>Mit den blauen Pfeilen kann man zwischen den Wochen (bzw. Monate / Jahre) wechseln.</p>';
  if ($isIndexPage) {      
      echo '
      <h3 class="font-semibold text-gray-900">Mehr Infos</h3>
      <p>Weitere Infos und Verbrauchsstatistiken findest du auf der Statistikseite</p>
      <a href="../verbrauch/'.$statisticLink.'" class="flex items-center font-medium text-blue-600 hover:text-blue-700">Statistik '.getSvg(whichSvg:Svg::ArrowRight).'</a>
    ';
    }
    echo '
      </div>
  <div data-popper-arrow></div>
</div>';  
  echo getHr().'<br>';
}


function getWattSum(object $dbConn, int $userid, Param $param, string $dayA, string $dayB, bool $kWh=false) { // returns two values
  $sql_where = ' WHERE `userid` = "'.$userid.'" AND `zeit` >= "'.$dayA.' 00:00:00" AND `zeit` <= "'.$dayB.' 23:59:59";';
  if ($param === Param::cost) { // cost param is handled differently
    $resultKunden = $dbConn->query('SELECT `priceConsHt`,`priceConsNt`, `priceGen` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
    if ($resultKunden->num_rows !== 1) {
        printRawErrorAndDie('Error', 'no config data');
    } 
    $rowKunden = $resultKunden->fetch_assoc();

    $sql = 'SELECT SUM(`consNtDiff`) as `sumConsNtDiff`, SUM(`consHtDiff`) as `sumConsHtDiff`, SUM(`genDiff`) as `sumGenDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
    $result = $dbConn->query($sql.$sql_where); // returns only one row
    $row = $result->fetch_assoc();

    if ($row['sumConsNtDiff'] + $row['sumConsHtDiff'] + $row['sumGenDiff'] < 0.001) { // don't have the info for old values
      return [' ', ' ']; // not really nice, returning empty string
    }

    $costTotal = round( -1.0 * 
                      ((($row['sumConsNtDiff'])*$rowKunden['priceConsNt']) +
                        (($row['sumConsHtDiff'])*$rowKunden['priceConsHt']) -
                        (($row['sumGenDiff']   )*$rowKunden['priceGen']   )), 2);
    
    $aveCost = 0.0; // average cost per day
    if ($row['sumZeitDiff'] > 0) {
      $aveCost = round(24*3600 / $row['sumZeitDiff'] * $costTotal,2);
    }
    return [$costTotal, $aveCost];
  }
  elseif ($param === Param::cons)  { $paramGen = Param::gen;}
  elseif ($param === Param::consNt){ $paramGen = Param::genNt;}
  elseif ($param === Param::consHt){ $paramGen = Param::genHt;}  
  
  $sql[0] = 'SELECT SUM(`'.$param->name.   'Diff`) as `sumDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
  $sql[1] = 'SELECT SUM(`'.$paramGen->name.'Diff`) as `sumDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
  
  $watt = [0, 0];
  for ($i = 0; $i < 2; $i++) {
    $result = $dbConn->query($sql[$i].$sql_where); // returns only one row
    $row = $result->fetch_assoc();
    if($kWh) {
      $watt[$i] = $row['sumDiff']; // returning the absolute value instead of the averaged one (in kWh)
    } else {
      if ($row['sumZeitDiff'] <= 0) { // divide by 0 exception
        return [' ', ' ']; // not really nice, returning strings
      }
      $watt[$i] = round($row['sumDiff']*3600*1000 / $row['sumZeitDiff']);
    }
  }

  return $watt; 
}

/*  
  $kWh = [0, 0];
  for ($i = 0; $i < 2; $i++) {
    $result = $dbConn->query($sql[$i].$sql_where); // returns only one row
    $row = $result->fetch_assoc();    
    $kWh[$i] = $row['sumDiff']*3600*1000;
  }
*/

function getValues(
  object $dbConn, int $userid, 
  Timerange $timerange, Param $param, 
  DateTime $startDate
):array {
  $val_x = '[ ';
  $val_y = ['[ ', '[ ']; // arrays: consumption is entry0, generation is entry1
  $val_y_ave = ['[ ', '[ '];
  $ave = [0.0, 0.0];
  $numOfEntries = 0;
  $weekDayOffset = 0;
  
  $year = (int)$startDate->format('Y'); // startDate year
  $month = (int)$startDate->format('m');  

  if ($timerange === Timerange::Year) { // generates one value per month
    $numOfEntries = 12;
    $ave = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$year.'-01-01', dayB:$year.'-12-31');
    for ($month = 1; $month <= 12; $month++) {
      $dayStrA = $year.'-'.$month.'-01';
      $lastDay = (int)date_create('last day of '.$year.'-'.$month)->format('d');
      $dayStrB = $year.'-'.$month.'-'.$lastDay;
      $tmp_arr = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStrA, dayB:$dayStrB);
      // $tmp_arr = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStrA, dayB:$dayStrB, kWh:true);
      $val_y[0] .= $tmp_arr[0].', ';
      $val_y[1] .= $tmp_arr[1].', ';
      $val_y_ave[0] .= $ave[0].', ';
      $val_y_ave[1] .= $ave[1].', ';
    }
    $val_x .= '"Jan", "Feb", "Mär", "Apr", "Mai", "Jun", "Jul", "Aug", "Sep", "Okt", "Nov", "Dez", ';// need german short names, not using format('M')
  } elseif ($timerange === Timerange::Month) { // maybe to do: could switch to date->modify method    
    $startDay = date_create($year.'-'.$month.'-01');
    $weekDayOffset = (int)$startDay->format('N') - 1; // 0 (for Monday) through 6 (for Sunday). Colors are matching between week and month
    $lastDay = (int)date_create('last day of '.$year.'-'.$month)->format('d');
    $ave = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$year.'-'.$month.'-01', dayB:$year.'-'.$month.'-'.$lastDay);
    for ($day = 1; $day <= $lastDay; $day++) { // 1 to 28 (for February)
      $dayStr = $year.'-'.$month.'-'.$day;
      $tmp_arr = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStr, dayB:$dayStr);
      $val_y[0] .= $tmp_arr[0].', ';
      $val_y[1] .= $tmp_arr[1].', ';
      $val_y_ave[0] .= $ave[0].', ';
      $val_y_ave[1] .= $ave[1].', ';
      $val_x .= $day.', ';
      $numOfEntries++;
    }
  } elseif ($timerange === Timerange::Week) {
    $numOfEntries = 7;
    $endDay = clone $startDate; // clone is needed here
    $endDay->modify('+6 days');
    $ave = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$startDate->format('Y-m-d'), dayB:$endDay->format('Y-m-d'));
    for ($day = 1; $day <= $numOfEntries; $day++) {
      $dayStr = $startDate->format('Y-m-d');
      $tmp_arr = getWattSum(dbConn:$dbConn, userid:$userid, param:$param, dayA:$dayStr, dayB:$dayStr);
      $val_y[0] .= $tmp_arr[0].', ';
      $val_y[1] .= $tmp_arr[1].', ';
      $val_y_ave[0] .= $ave[0].', ';
      $val_y_ave[1]  .= $ave[1].', ';
      $startDate->modify('+1 days');
    }
    $val_x .= '"Mo", "Di", "Mi", "Do", "Fr", "Sa", "So", ';
  }
  
  $val_y[0] = substr($val_y[0], 0, -2).' ]'; // remove the last two caracters (a comma-space) and add the brackets after
  $val_y[1] = substr($val_y[1] , 0, -2).' ]';
  $val_y_ave[0] = substr($val_y_ave[0], 0, -2).' ]';
  $val_y_ave[1] = substr($val_y_ave[1] , 0, -2).' ]';
  $val_x = substr($val_x, 0, -2).' ]';
  return [$numOfEntries, $val_x, $val_y[0], $val_y[1], $val_y_ave[0], $val_y_ave[1], $ave[0], $ave[1], $weekDayOffset];
}

// prints header with css/js and body, container-div and h1 title
function printBeginOfPage_v2(string $site, string $title=''):void {
  $SITE_TITLES = array(
    'index.php' => 'Übersicht',
    'settings.php' => 'Einstellungen',
    'login.php' => 'Login, Logout',
    'statistic.php' => 'Statistiken',
    'status.php' => 'Status',
    'contact.php' => 'Kontaktformular',
    'now.php' => 'Aktuelle Werte'
  );
  echo '<!DOCTYPE html>
  <html>
  <head>
  <meta charset="utf-8">
  ';
  $scripts = '';
  if (($site === 'index.php') or ($site === 'statistic.php')) {
    $scripts = '<script src="../verbrauch/script/chart.umd.js"></script>
  <script src="../verbrauch/script/moment.min.mine.js"></script>
  <script src="../verbrauch/script/chartjs-adapter-moment.mine.js"></script>';
  } 
  
  echo '<title>StromMesser '.$SITE_TITLES[$site].'</title>';
  if ($site === 'now.php') {
    echo '
  <meta http-equiv="refresh" content="90">';
  }
  echo '
  <meta name="description" content="zeigt deinen Energieverbrauch">  
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../verbrauch/strommesser.css" type="text/css">
  <script src="../verbrauch/script/flowbite.min.js"></script>
  '.$scripts.'
  </head>
  <body>
  ';
  // possible status-notification
  // echo '<div style="width: 80%; top:7rem; min-height:3rem; padding:0 20px; text-align:center; font-size:larger; line-height:3rem; border-radius:3rem; box-sizing:border-box; color: rgb(25, 99, 132);border:2px solid rgb(25, 99, 132);  position:relative; display:block; background-color:rgba(255, 255, 255, 0.8); z-index:2; transform:rotate(-10deg);"><b>Offline:</b> Vom 22.9.23 bis ca. 3.10.23 werden keine Daten ausgelesen.</div>';
  
  printNavMenu_v2(site:$site, title:$title);
  echo '
  <div class="container mx-auto px-4 py-2 lg text-center mt-14 scroll-mt-14" id="anchorTopOfPage">
  ';
  return;
}

function printNavMenu_v2 (string $site, string $title): void {
  $topLevelSites = array( // NB: partial repetition of SITE_TITLES (only those within 'verbrauch' directory)
    ['index.php', 'Übersicht'],
    ['statistic.php', 'Statistiken'],
    ['settings.php', 'Einstellungen'],
    ['now.php', 'Aktuelle Werte'],
    ['#', '&nbsp;'],
    ['login.php?do=2', 'LogOut']    
  );  
  echo '
<nav class="border-gray-400 rounded bg-gray-100 px-2 sm:px-4 fixed w-full top-0 left-0" aria-label="Breadcrumb">
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
      ['#myChart', 'Leistungsübersicht'],
      ['#anchorWcons', 'Wöchentlich'],
      ['#anchorMcons', 'Monatlich'],
      ['#anchorYcons', 'Jährlich']
    );
    $siteName = 'Übersicht';
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
  } elseif ($site === 'now.php') {
    $siteName = 'Aktuelle Werte';
  } elseif ($site === 'login.php') {
    $siteName = 'Login';
  }
  if ($title) { $siteName = $title; }
  printInPageNav(inPageTargets:$inPageTargets, siteName:$siteName);
  echo '</ol>
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
function makeSafeFloat($unsafe, int $length):float {  
  $unsafe = filter_var(value:substr(string:$unsafe, offset:0, length:$length), filter:FILTER_SANITIZE_NUMBER_FLOAT, options:FILTER_FLAG_ALLOW_FRACTION); // sanitize a length-limited variable
  if (filter_var(value:$unsafe, filter:FILTER_VALIDATE_FLOAT)) { 
    return (float)$unsafe;
  } else { 
    return 0.0;
  }  
}

// returns a 'safe' string. Not that much to do though for a string
function makeSafeStr($unsafe, int $length):string {
  return htmlentities(string: substr(string:$unsafe, offset:0, length:$length)); // length-limited variable, HTML encoded
}

// returns a 'safe' character-as-hex value
function makeSafeHex($unsafe, int $length):string {  
  $unsafe = substr(string:$unsafe, offset:0, length:$length); // length-limited variable  
  if (ctype_xdigit(text:$unsafe)) {
    return (string)$unsafe;
  } else {
    return '0';
  }
}

// checks whether a get/post/cookie variable exists and makes it safe if it does. If not, returns 0
function safeIntFromExt(string $source, string $varName, int $length):int {
  if (($source === 'GET') and isset($_GET[$varName])) {
    return makeSafeInt(unsafe:$_GET[$varName], length:$length);    
  } elseif (($source === 'POST') and isset($_POST[$varName])) {
    return makeSafeInt(unsafe:$_POST[$varName], length:$length);    
  } elseif (($source === 'COOKIE') and isset($_COOKIE[$varName])) {
    return makeSafeInt(unsafe:$_COOKIE[$varName], length:$length);  
  } else {
    return 0;
  }
}

function safeFloatFromExt(string $source, string $varName, int $length):float {
  if (($source === 'GET') and isset($_GET[$varName])) {
    return makeSafeFloat(unsafe:$_GET[$varName], length:$length);    
  } elseif (($source === 'POST') and isset($_POST[$varName])) {
    return makeSafeFloat(unsafe:$_POST[$varName], length:$length);    
  } elseif (($source === 'COOKIE') and isset($_COOKIE[$varName])) {
    return makeSafeFloat(unsafe:$_COOKIE[$varName], length:$length);  
  } else {
    return 0.0;
  }
}

function safeHexFromExt(string $source, string $varName, int $length):string {
  if (($source === 'GET') and isset($_GET[$varName])) {
     return makeSafeHex(unsafe:$_GET[$varName], length:$length);
   } elseif (($source === 'POST') and isset($_POST[$varName])) {
     return makeSafeHex(unsafe:$_POST[$varName], length:$length);
   } elseif (($source === 'COOKIE') and isset($_COOKIE[$varName])) {
     return makeSafeHex(unsafe:$_COOKIE[$varName], length:$length);
   } else {
     return '0';
   }
}

function safeStrFromExt(string $source, string $varName, int $length):string {
  if (($source === 'GET') and isset($_GET[$varName])) {
     return makeSafeStr(unsafe:$_GET[$varName], length:$length);
   } elseif (($source === 'POST') and isset($_POST[$varName])) {
     return makeSafeStr(unsafe:$_POST[$varName], length:$length);
   } elseif (($source === 'COOKIE') and isset($_COOKIE[$varName])) {
     return makeSafeStr(unsafe:$_COOKIE[$varName], length:$length);
   } else {
     return '';
   }
}

function limitInt(int $input, int $lower, int $upper):int {
  return min(value: max(value:$input, values:$lower), values:$upper);  
}

 