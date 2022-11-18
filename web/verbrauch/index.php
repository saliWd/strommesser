<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

function printBeginOfPage_index(bool $enableAutoReload, string $timerange):void {
  echo '<!DOCTYPE html><html><head>
  <meta charset="utf-8" />
  <title>StromMesser</title>
  <meta name="description" content="zeigt deinen Energieverbrauch" />  
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="css/font.css" type="text/css" />
  <link rel="stylesheet" href="css/skeleton.css" type="text/css" />
  <script src="script/chart.min.js"></script>
  <script src="script/moment.min.mine.js"></script>
  <script src="script/chartjs-adapter-moment.mine.js"></script>';
  if ($enableAutoReload) {
    echo '<meta http-equiv="refresh" content="40; url=https://widmedia.ch/wmeter/index.php?autoreload=1'.$timerange.'">';
  }
  echo '
  </head><body>';
  printNavMenu(getCurrentSite());
  echo '
  <div class="section noBottom">
  <div class="container">
  <h3>StromMesser</h3>';
  return;
}


// returns the time range to be displayed as int. Possible values are: 1 (for last 1 hour), 6, 24, 25. 25 means: all data
function getTimeRange():int {
  $returnVal = 6;  // default time range
  $unsafeInt = safeIntFromExt('GET', 'rangeSelect', 2);
  if (($unsafeInt === 1) or ($unsafeInt === 6) or ($unsafeInt === 24) or ($unsafeInt === 25)) {
    $returnVal = $unsafeInt; 
  }
  return $returnVal;
}

$autoreload = safeIntFromExt('GET', 'autoreload', 1);
$timeSelected = getTimeRange();
$enableAutoReload = ($autoreload === 1);
$device = 'austr10'; // TODO: device as variable

$resultCnt = $dbConn->query('SELECT COUNT(*) as `total` FROM `wmeter` WHERE `device` = "'.$device.'" LIMIT 1;'); // guaranteed to return one row
$resultFreshest = $dbConn->query('SELECT `zeit` FROM `wmeter` WHERE `device` = "'.$device.'" ORDER BY `zeit` DESC LIMIT 1;'); // cannot combine those two

$rowCnt = $resultCnt->fetch_assoc(); // returns one row only
$rowFreshest = $resultFreshest->fetch_assoc(); // returns 0 or 1 row
$totalCount = $rowCnt['total'];

printBeginOfPage_index($enableAutoReload, '&rangeSelect='.$timeSelected);
if ($totalCount > 0) {// this may be 0. Can't 
  $zeitNewest = date_create($rowFreshest['zeit']);    
  if ($timeSelected < 25) {
    $zeitOldest = date_create($rowFreshest['zeit']);
    $zeitOldest->modify('-'.$timeSelected.' hours');
    $zeitOldestString = $zeitOldest->format('Y-m-d H:i:s');
  } else {
    $zeitOldestString = '2020-01-01 08:00:00'; // some arbitrary date in the past
  }

  $QUERY_LIMIT = 10000; // have some upper limit, both for js and db-performance
  $GRAPH_LIMIT = 3; // does not make sense to display a graph otherwise

  $sql = 'SELECT `consumption`, `zeit`, `aveConsDiff`, `aveZeitDiff` ';
  $sql .= 'from `wmeter` WHERE `device` = "'.$device.'" AND `zeit` > "'.$zeitOldestString.'" ';
  $sql .= 'ORDER BY `zeit` DESC LIMIT '.$QUERY_LIMIT.';';    

  $result = $dbConn->query($sql);
  $result->data_seek($result->num_rows - 1); // skip to the last entry of the rows
  $rowOldest = $result->fetch_assoc();
  $result->data_seek(0); // go back to the first row

  $rowNewest = $result->fetch_assoc(); // TODO: could maybe remove this now (combine freshest and newest)
  $queryCount = $result->num_rows; // this may be < graph-limit ( = display at least the newest) or >= graph-limit ( = all good)

  if ($rowNewest['aveZeitDiff'] > 0) { // divide by 0 exception
      $newestConsumption = round($rowNewest['aveConsDiff']*3600*1000 / $rowNewest['aveZeitDiff']); // kWh compared to seconds
  } else { $newestConsumption = 0.0; }
  
  $zeitString = 'um '.$zeitNewest->format('Y-m-d H:i:s');
  if (date('Y-m-d') === $zeitNewest->format('Y-m-d')) { // same day
    $zeitString = 'heute um '.$zeitNewest->format('H:i:s');
  }
  echo '<div class="row twelve columns"><hr>Verbrauch: <b>'.$newestConsumption.'W</b> '.$zeitString.'<hr></div>';

  if ($queryCount >= $GRAPH_LIMIT) {
    $axis_x = ''; // rightmost value comes first. Remove something again after the while loop
    $val_y0_consumption = '';
    $val_y1_watt = '';
    
    while ($row = $result->fetch_assoc()) { // did already fetch the newest one. At least 2 remaining  
      $consumption = $row['consumption'] - $rowOldest['consumption']; // to get a relative value (and not some huge numbers)
      if ($row['aveZeitDiff'] > 0) { // divide by 0 exception
        $watt = max(round($row['aveConsDiff']*3600*1000 / $row['aveZeitDiff']), 1.0); // max(val,1.0) because 0 in log will not be displayed correctly
      } else { $watt = 0; }
      
      // revert the ordering
      $axis_x = 'new Date("'.$row['zeit'].'"), '.$axis_x; // new Date("2020-03-01 12:00:12")
      $val_y0_consumption = $consumption.', '.$val_y0_consumption;
      $val_y1_watt = $watt.', '.$val_y1_watt;
    } // while 
    // remove the last two caracters (a comma-space) and add the brackets before and after
    $axis_x = '[ '.substr($axis_x, 0, -2).' ]';
    $val_y0_consumption = '[ '.substr($val_y0_consumption, 0, -2).' ]';
    $val_y1_watt = '[ '.substr($val_y1_watt, 0, -2).' ]';
    
    // TODO: add some text about the absolute value (of kWh)

    echo '<div class="row twelve columns"><canvas id="myChart" width="600" height="300"></canvas></div>      
    <script>
    const ctx = document.getElementById("myChart");
    const labels = '.$axis_x.';
    const data = {
      labels: labels,
      datasets: [{
        label: "Verbrauch [W]",
        data: '.$val_y1_watt.',
        yAxisID: "yleft",
        backgroundColor: "rgb(25, 99, 132)",
        showLine: false
      },
      {
        label: "Verbrauch total [kWh]",
        data: '.$val_y0_consumption.',
        yAxisID: "yright",
        backgroundColor: "rgba(255, 99, 132, 0.4)",
        showLine: false
      }
    ],
    };
    const config = {
      type: "line",
      data: data,
      options: {
        scales: {
          x: { type: "time", 
            time: { '; 
          if ($timeSelected === 1) {
            echo 'unit: "minute"';
          } elseif ($timeSelected === 25) {
            echo 'unit: "day"';
          } else {
            echo 'unit: "hour"';
          }            
          echo ' }
              },
          yleft: { type: "logarithmic", position: "left", ticks: {color: "rgb(25, 99, 132)"} },
          yright: { type: "linear",  position: "right", ticks: {color: "rgba(255, 99, 132, 0.8)"}, grid: {drawOnChartArea: false} }
        }
      }
    };
    const myChart = new Chart( document.getElementById("myChart"), config );
    </script>';
  } else {
    echo '<div class="row twelve columns"> - weniger als '.$GRAPH_LIMIT.' Einträge - </div>';
  }    
} else {
  echo '<div class="row twelve columns"> - noch keine Einträge - </div>';
}

$checkedText = '';
if($enableAutoReload) {
  $checkedText = ' checked';
}

// TODO: add icons, change design, spacing of buttons
// TODO: depending on the number of entries, some ranges cannot be selected
$submitTexts = array (
  '1' => array('1','1 h',''),
  '6' => array('6','6 h',''),
  '24' => array('24','24 h',''),
  '25' => array('25','alles','')
);
$submitTexts[$timeSelected][2]  = ' class="active"'; // highlight the selected one
echo '
<script>
function setValAndSubmit(valueString){
    document.getElementById(\'hiddentext\').value=valueString;
    document.getElementById(\'timerangeform\').submit();
}
</script>';
echo '<div class="row twelve columns"><form id="timerangeform" action="index.php" method="get">
<input type="checkbox" id="autoreload" name="autoreload" value="1" onChange="setValAndSubmit(\''.$timeSelected.'\')" '.$checkedText.'> reload';
foreach ($submitTexts as $submitText) {
  echo '<button type="button" onclick="setValAndSubmit(\''.$submitText[0].'\')" '.$submitText[2].'>'.$submitText[1].'</button>';
}
echo '<input type="text" id="hiddentext" name="rangeSelect" value="invalidRange" hidden></form></div>
<div class="row twelve columns">Insgesamt '.$totalCount.' Einträge</div>';

?>
<div class="row twelve columns">&nbsp;</div>
</div></div></body></html>
