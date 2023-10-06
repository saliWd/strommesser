<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

// returns the time range to be displayed as int. Possible values are: 1 (for last 1 hour), 6, 24, 25. 25 means: all data
function getTimeRange():int {
  $returnVal = 6;  // default time range
  $unsafeInt = safeIntFromExt(source:'GET',varName:'range',length:2);
  if (($unsafeInt === 1) or ($unsafeInt === 6) or ($unsafeInt === 24) or ($unsafeInt === 25)) {
    $returnVal = $unsafeInt; 
  }
  return $returnVal;
}

$reload = safeIntFromExt(source:'GET',varName:'reload',length:1);
$timeSelected = getTimeRange();
$enableReload = ($reload === 1);
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$resultCnt = $dbConn->query('SELECT COUNT(*) as `total` FROM `verbrauch` WHERE `userid` = "'.$userid.'" LIMIT 1;'); // guaranteed to return one row
$resultFreshest = $dbConn->query('SELECT `zeit` FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `zeit` DESC LIMIT 1;'); // cannot combine those two

$rowCnt = $resultCnt->fetch_assoc(); // returns one row only
$rowFreshest = $resultFreshest->fetch_assoc(); // returns 0 or 1 row
$totalCount = $rowCnt['total'];

$refreshMeta = '';
if ($enableReload) { $refreshMeta = '<meta http-equiv="refresh" content="40; url=https://strommesser.ch/verbrauch/index.php?reload=1&range='.$timeSelected.'">'."\n"; }
printBeginOfPage_v2(site:'index.php',  refreshMeta:$refreshMeta);
if ($totalCount > 0) {// this may be 0
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

  $sql = 'SELECT `consumption`, `gen`, `zeit`, `consDiff`, `zeitDiff`, `genDiff`, `consNt`, `consHt` ';
  $sql .= 'from `verbrauch` WHERE `userid` = "'.$userid.'" AND `zeit` > "'.$zeitOldestString.'" ';
  $sql .= 'ORDER BY `zeit` DESC LIMIT '.$QUERY_LIMIT.';';

  $result = $dbConn->query($sql);
  $result->data_seek($result->num_rows - 1); // skip to the last entry of the rows
  $rowOldest = $result->fetch_assoc();
  $result->data_seek(0); // go back to the first row

  $rowNewest = $result->fetch_assoc();
  $queryCount = $result->num_rows; // this may be < graph-limit ( = display at least the newest) or >= graph-limit ( = all good)

  // get some account specific infos from the db
  $resultKunden = $dbConn->query('SELECT `priceConsHt`,`priceConsNt`, `priceGen` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
  if ($resultKunden->num_rows !== 1) {
      printRawErrorAndDie('Error', 'no config data');
  } 
  $rowKunden = $resultKunden->fetch_assoc();

  // cost over the whole time range
  // for the oldest entries, I don't have the Nt/Ht/Gen information
  if ($rowOldest['consNt'] + $rowOldest['consHt'] + $rowOldest['gen'] > 0.001) { 
    $costValid = TRUE;
    $costTotal = round( -1.0 * 
                        ((($rowNewest['consNt'] - $rowOldest['consNt'])*$rowKunden['priceConsNt']) +
                         (($rowNewest['consHt'] - $rowOldest['consHt'])*$rowKunden['priceConsHt']) -
                         (($rowNewest['gen']    - $rowOldest['gen'])*   $rowKunden['priceGen']   )), 2);
  } else {
    $costValid = FALSE;
    $costTotal = 0.0;
  } 

  if ($rowNewest['zeitDiff'] > 0) { // divide by 0 exception
      $newestCons = round($rowNewest['consDiff']*3600*1000 / $rowNewest['zeitDiff']); // kWh compared to seconds
      $newestGen = round($rowNewest['genDiff']*3600*1000 / $rowNewest['zeitDiff']);
  } else { 
    $newestCons = 0.0;
    $newestGen = 0.0;
  }

  $zeitDiff = strtotime($rowNewest['zeit']) - strtotime($rowOldest['zeit']); // difference in seconds
  if ($zeitDiff > 0) { // divide by 0 exception
    $aveCons = round(($rowNewest['consumption'] - $rowOldest['consumption'])*3600*1000 / $zeitDiff); // kWh compared to seconds
    $aveGen = round(($rowNewest['gen'] - $rowOldest['gen'])*3600*1000 / $zeitDiff);
  } else { 
    $aveCons = 0.0;
    $aveGen = 0.0;
  }
  
  $zeitString = $zeitNewest->format('Y-m-d H:i');
  if (date('Y-m-d') === $zeitNewest->format('Y-m-d')) { // same day
    $zeitString = $zeitNewest->format('H:i');
  }
  // COLORS: consumption: red "text-red-500" = rgb(239 68 68); generation: green "text-green-600" = rgb(22 163 74);
  echo '<div class="flex">
    <div class="flex-auto text-left"><b><span class="text-green-600">'.$newestGen.'W</span> / <span class="text-red-500">'.$newestCons.'W</span></b></div>
    <div class="flex-auto text-center">'.$zeitString.'</div>
    <div class="flex-auto text-right">Ø: <b><span class="text-green-600">'.$aveGen.'W</span> / <span class="text-red-500">'.$aveCons.'W</span></b></div>
  </div>
  <hr>
  ';

  if ($queryCount >= $GRAPH_LIMIT) {   
    $axis_x = ''; // rightmost value comes first. Remove something again after the while loop
    $val_yr_cons_kwh = '';
    $val_yr_gen_kwh = '';
    $val_yr_cost = '';
    $val_yl_cons_ave = '';
    $val_yl_gen_ave = '';
    $val_yl_cons = '';
    $val_yl_gen = '';
    
    while ($row = $result->fetch_assoc()) { // did already fetch the newest one. At least 2 remaining  
      if ($row['zeitDiff'] > 0) { // divide by 0 exception
        // 0 in log will not be displayed correctly... values smaller than 10 will not be displayed (empty space ' ')
        $tmp = round($row['consDiff']*3600*1000 / $row['zeitDiff']);
        $watt = ( $tmp > 10 ) ? $tmp : ' ';
        $tmp = round($row['genDiff']*3600*1000 / $row['zeitDiff']);
        $gen = ($tmp > 10 ) ? $tmp : ' ';
      } else { 
        $watt = 10.0;
        $gen = 10.0;
      }
      
      // revert the ordering
      $axis_x = 'new Date("'.$row['zeit'].'"), '.$axis_x; // new Date("2020-03-01 12:00:12")
      $val_yr_cons_kwh = ($row['consumption'] - $rowOldest['consumption']) .', '.$val_yr_cons_kwh; // to get a relative value (and not some huge numbers)
      $val_yr_gen_kwh = ($row['gen'] - $rowOldest['gen']) .', '.$val_yr_gen_kwh;
      if($costValid) {
        $val_yr_cost = -1.0 * 
                    ((($row['consNt'] - $rowOldest['consNt'])*$rowKunden['priceConsNt']) +
                     (($row['consHt'] - $rowOldest['consHt'])*$rowKunden['priceConsHt']) -
                     (($row['gen'] - $rowOldest['gen'])*$rowKunden['priceGen'])) .', '.$val_yr_cost;
      } else {
        $val_yr_cost = ' , '.$val_yr_cost; // just empty string. No meaningful value available
      }
      $val_yl_cons_ave = $aveCons.', '.$val_yl_cons_ave;
      $val_yl_gen_ave = $aveGen.', '.$val_yl_gen_ave;
      $val_yl_cons = $watt.', '.$val_yl_cons;
      $val_yl_gen = $gen.', '.$val_yl_gen;
    } // while
    // remove the last two caracters (a comma-space) and add the brackets before and after
    $axis_x = '[ '.substr($axis_x, 0, -2).' ]';
    $val_yr_cons_kwh = '[ '.substr($val_yr_cons_kwh, 0, -2).' ]';
    $val_yr_gen_kwh = '[ '.substr($val_yr_gen_kwh, 0, -2).' ]';
    $val_yr_cost = '[ '.substr($val_yr_cost, 0, -2).' ]';
    $val_yl_cons_ave = '[ '.substr($val_yl_cons_ave, 0, -2).' ]';
    $val_yl_gen_ave = '[ '.substr($val_yl_gen_ave, 0, -2).' ]';
    $val_yl_cons = '[ '.substr($val_yl_cons, 0, -2).' ]';
    $val_yl_gen = '[ '.substr($val_yl_gen, 0, -2).' ]';
    
    // maybe: add some text about the absolute value (of kWh)
    echo '
    <canvas id="myChart" width="600" height="300" class="mb-2"></canvas>
    <script>
    const ctx = document.getElementById("myChart");
    const labels = '.$axis_x.';
    const data = {
      labels: labels,
      datasets: [{
        label: "Verbrauch total [kWh]",
        data: '.$val_yr_cons_kwh.',
        yAxisID: "yright",
        backgroundColor: "rgba(239, 68, 68, 0.2)",
        showLine: false
      },
      {
        label: "Einspeisung total [kWh]",
        data: '.$val_yr_gen_kwh.',
        yAxisID: "yright",
        backgroundColor: "rgba(22, 163, 74, 0.2)",
        showLine: false
      },
      {
        label: "Durchschnittsverbrauch [W]",
        data: '.$val_yl_cons_ave.',
        yAxisID: "yleft",
        borderColor: "rgba(239, 68, 68, 0.8)",
        backgroundColor: "rgb(255,255,255)",
        borderWidth: 2,
        borderDash: [10, 5],
        pointStyle: false
      },
      {
        label: "Durchschnitt Einspeisung [W]",
        data: '.$val_yl_gen_ave.',
        yAxisID: "yleft",
        borderColor: "rgba(22, 163, 74, 0.8)",
        backgroundColor: "rgb(255,255,255)",
        borderWidth: 2,
        borderDash: [10, 5],
        pointStyle: false
      },
      {
        label: "Verbrauch [W]",
        data: '.$val_yl_cons.',
        yAxisID: "yleft",
        backgroundColor: "rgba(239, 68, 68, 0.8)",
        showLine: false
      },
      {
        label: "Einspeisung [W]",
        data: '.$val_yl_gen.',
        yAxisID: "yleft",
        backgroundColor: "rgba(22, 163, 74, 0.8)",
        showLine: false
      }      
    ],
    };
    const config = {
      type: "line",
      data: data,
      options: {
        plugins: {
          legend: {
            display: false
          }
        },
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
          yright: { type: "linear",  position: "right", ticks: {color: "rgba(25, 99, 132, 0.6)"}, grid: {drawOnChartArea: false} }
        }
      }
    };
    const myChart = new Chart( document.getElementById("myChart"), config );
    </script>
    <br><br>';
    
    if($costValid) {
      if ($costTotal >= 0.0) {
        $costClass = 'text-green-600';
        $costText  = 'Ertrag';
      } else {
        $costClass = 'text-red-500';
        $costText  = 'Kosten';
      }
      echo '
      <div class="flex">
        <div class="flex-auto text-left"><b><span class="'.$costClass.'">'.$costText.' [CHF]</span></b></div>
        <div class="flex-auto text-center">&nbsp;</div>
        <div class="flex-auto text-right"><b><span class="'.$costClass.'">'.$costTotal.'.-</span></b></div>
      </div>
      <hr>
      <canvas id="myChartCost" width="600" height="200" class="mb-2"></canvas>
      <script>
      const ctxCost = document.getElementById("myChartCost");
      const labelsCost = '.$axis_x.';
      const dataCost = {
        labels: labelsCost,
        datasets: [{
          label: "Kosten [CHF]",
          data: '.$val_yr_cost.',
          yAxisID: "yrightCost",
          backgroundColor: "rgba(0, 0, 0, 0.2)",
          showLine: false
        }      
      ],
      };
      const configCost = {
        type: "line",
        data: dataCost,
        options: {
          plugins: {
            legend: {
              display: false
            }
          },
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
            yrightCost: { type: "linear",  position: "right", ticks: {color: "rgba(0, 0, 0, 0.6)"}, grid: {drawOnChartArea: false} }
          }
        }
      };
      const myChartCost = new Chart( document.getElementById("myChartCost"), configCost );
      </script>';
    }  else {
      echo ' - keine Kosten-Infos verfügbar - <br><br><br>';    
    }
  } else {
    echo '<br><br> - weniger als '.$GRAPH_LIMIT.' Einträge - <br><br><br>';
  }    
} else {
  echo '<br><br> - noch keine Einträge - <br><br><br>';
}

$checkedText = '';
$reloadLink = '';
$reloadLinkChange = '?range='.$timeSelected.'&reload=1';
if($enableReload) {
  $checkedText = ' checked';
  $reloadLink = '&reload=1';
  $reloadLinkChange = '?range='.$timeSelected;
}

$submitTexts = array (
  '1' => array('1','1 h','class="btn"'),
  '6' => array('6','6 h','class="btn"'),
  '24' => array('24','24 h','class="btn"'),
  '25' => array('25','alles','class="btn"')
);
$submitTexts[$timeSelected][2]  = 'class="btn-diff"'; // highlight the selected one
echo '
<a href="index.php'.$reloadLinkChange.'"><input'.$checkedText.' id="reload-checkbox" type="checkbox" value="" class="chkbox-link"></a><label for="reload-checkbox" class="chkbox-link-label"><a href="index.php'.$reloadLinkChange.'">reload </a></label>';
foreach ($submitTexts as $submitText) {
  echo '<a id="range_'.$submitText[0].'h_link" href="index.php?range='.$submitText[0].$reloadLink.'" '.$submitText[2].'>'.$submitText[1].'</a>';
}
echo '<br><br>
<hr>
<div class="flex items-center">
  <div class="text-sm font-light text-gray-500">
    Info / Details:
    <button data-popover-target="popover-descriptionIndex" data-popover-placement="bottom-end" type="button">'.getSvg(whichSvg:Svg::QuestionMark).'<span class="sr-only">Info</span></button>
  </div>
  <div class="flex-auto text-right">Insgesamt '.$totalCount.' Einträge</div>
</div>
<div data-popover id="popover-descriptionIndex" role="tooltip" class="text-left absolute z-10 invisible inline-block text-sm font-light text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-sm opacity-0 w-72">
    <div class="p-3 space-y-2">
        <h3 class="font-semibold text-gray-900">Leistungsmessung</h3>
        <p>
          Alle zwei Minuten wird der Energiezähler ausgelesen. Dies erfolgt mit einer Genauigkeit von 0.001 kWh, d.h. 1 Wh = 3600 W über einen Zeitraum von ca. zwei Minuten = 120 Sekunden. Für die einzelne Messung entspricht das einer Auflösung von ca. 30 W. Es wird sowohl der Verbrauch als auch die Einspeisung ausgelesen. In dieser Grafik sieht man den totalen Verbrauch / Einspeisung. Also Niedertarif (NT) und Hochtarif (HT) zusammen. <br>
          Auf der linken Skala werden die aktuellen Werte logarithmisch in Watt aufgetragen, auf der rechten Skala die summierten Werte linear in kWh.
        </p>
        <h3 class="font-semibold text-gray-900">Aktueller Verbrauch (rot, linke Skala)</h3>
        <p>Der aktuelle Verbrauch (in W-Auflösung) wird rot und auf der linken Skala aufgetragen. Diese Skala ist logarithmisch.</p>
        <h3 class="font-semibold text-gray-900">Verbrauch Total (blass-rot, rechte Skala)</h3>
        <p>Der Totalverbrauch (in Wh-Auflösung) wird blass-rot und linear auf der rechten Skala aufgetragen. Diese Skala beginnt über den gewählten Zeitraum immer bei 0 kWh.</p>
        
        <h3 class="font-semibold text-gray-900">Aktuelle Einspeisung (grün, linke Skala)</h3>
        <p>Die aktuelle Einspeisung (in W-Auflösung) wird grün und auf der linken Skala aufgetragen. Diese Skala ist logarithmisch.</p>
        <h3 class="font-semibold text-gray-900">Einspeisung Gesamt (blass-grün, rechte Skala)</h3>
        <p>Die gesamte Einspeisung (in Wh-Auflösung) wird blass-grün und linear auf der rechten Skala aufgetragen. Diese Skala beginnt über den gewählten Zeitraum immer bei 0 kWh.</p>

        <h3 class="font-semibold text-gray-900">Zeitliche Auflösung (x-Achse)</h3>
        <p>Innerhalb der letzten 24 Stunden wird jede Messung dargestellt. Ältere Messungen nur noch mit einem Punkt pro Stunde (Zeitraum 24 Stunden bis 72 Stunden), bzw. mit einem Punkt pro Tag (älter).</p>
        <h3 class="font-semibold text-gray-900">Mehr Infos</h3>
        <p>Weitere Infos und Verbrauchsstatistiken findest du auf der Statistikseite</p>
        <a href="statistic.php" class="flex items-center font-medium text-blue-600 hover:text-blue-700">Statistik '.getSvg(whichSvg:Svg::ArrowRight).'</a>
    </div>
    <div data-popper-arrow></div>
</div>
<br>';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Week,  param:Param::cons, goBack:safeIntFromExt('GET','goBackWcons', 2), isIndexPage:TRUE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Month, param:Param::cons, goBack:safeIntFromExt('GET','goBackMcons', 2), isIndexPage:TRUE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Year,  param:Param::cons, goBack:safeIntFromExt('GET','goBackYcons', 2), isIndexPage:TRUE);
echo '<p>Weitere Auswertungen findest du auf der<a href="statistic.php" class="font-medium text-blue-600 hover:text-blue-700">'.getSvg(whichSvg:Svg::ArrowRight, classString:'w-6 h-6 inline').'Statistikseite</a></p>';

?>
<br><br>
</div></body></html>
