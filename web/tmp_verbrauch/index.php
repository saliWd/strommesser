<?php declare(strict_types=1); 
require_once 'functions.php';
$dbConn = initialize();

$timeSelected = getTimeRange(defaultVal: 1);
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$resultCnt = $dbConn->query('SELECT COUNT(*) as `total` FROM `verbrauch_26` WHERE `userid` = "'.$userid.'" LIMIT 1;'); // guaranteed to return one row
$resultFreshest = $dbConn->query('SELECT `zeit` FROM `verbrauch_26` WHERE `userid` = "'.$userid.'" ORDER BY `zeit` DESC LIMIT 1;'); // cannot combine those two

$rowCnt = $resultCnt->fetch_assoc(); // returns one row only
$rowFreshest = $resultFreshest->fetch_assoc(); // returns 0 or 1 row
$totalCount = $rowCnt['total'];

printBeginOfPage_v2(site:'index.php');

$tabTexts = array (  
  '1'   => array('1',  'Tag',  'border-transparent hover:text-gray-600 hover:border-gray-300'),
  '7'   => array('7',  'Woche','border-transparent hover:text-gray-600 hover:border-gray-300'),
  '30'  => array('30', 'Monat','border-transparent hover:text-gray-600 hover:border-gray-300'),
  '365' => array('365','Jahr', 'border-transparent hover:text-gray-600 hover:border-gray-300')
);
$tabTexts[$timeSelected][2]  = 'border-blue-600 text-blue-600 active'; // highlight the selected one
echo '
<div class="text-sm font-medium text-center text-gray-500 border-b border-gray-200 mb-4">
    <ul class="flex flex-wrap -mb-px">';
foreach ($tabTexts as $tabText) {
  echo '
        <li class="mr-2">
            <a href="index.php?range='.$tabText[0].'" class="inline-block p-4 border-b-2 rounded-t-lg '.$tabText[2].'">'.$tabText[1].'</a>
        </li>';
}
echo '
    </ul>
</div>
';

if ($totalCount > 0) {// this may be 0
  $zeitNewest = date_create($rowFreshest['zeit']);    
  $zeitOldest = date_create($rowFreshest['zeit']);
  $zeitOldest->modify('-'.$timeSelected.' days');
  $zeitOldestString = $zeitOldest->format('Y-m-d H:i:s');
  

  $QUERY_LIMIT = 10000; // have some upper limit, both for js and db-performance
  $GRAPH_LIMIT = 3; // does not make sense to display a graph otherwise

  $sql = 'SELECT `con`, `gen`, `zeit`, `conDiff`, `conRate`, `zeitDiff`, `genDiff`, `genRate` ';
  $sql .= "from `verbrauch_26` WHERE `userid` = \"$userid\" AND `zeit` > \"$zeitOldestString\" ";
  $sql .= "ORDER BY `zeit` DESC LIMIT $QUERY_LIMIT;";

  $result = $dbConn->query(query:$sql);
  $result->data_seek(offset:$result->num_rows - 1); // skip to the last entry of the rows
  $rowOldest = $result->fetch_assoc();
  $result->data_seek(offset: 0); // go back to the first row

  $rowNewest = $result->fetch_assoc();
  $queryCount = $result->num_rows; // this may be < graph-limit ( = display at least the newest) or >= graph-limit ( = all good)

  // cost over the whole time range
  $costTotal = round(num:-1.0 * ( // TODO: is this correct?
                      ($rowNewest['con']*$rowNewest['conRate'] - $rowOldest['con']*$rowOldest['conRate']) - 
                      ($rowNewest['gen']*$rowOldest['genRate'] - $rowOldest['gen']*$rowOldest['genRate'])
                    ), precision: 2);

  if ($rowNewest['zeitDiff'] > 0) { // divide by 0 exception
      $newestCon = round($rowNewest['conDiff']*3600*1000 / $rowNewest['zeitDiff']); // kWh compared to seconds
      $newestGen = round($rowNewest['genDiff']*3600*1000 / $rowNewest['zeitDiff']);
  } else { 
    $newestCon = 0.0;
    $newestGen = 0.0;
  }

  $zeitDiff = strtotime($rowNewest['zeit']) - strtotime($rowOldest['zeit']); // difference in seconds
  if ($zeitDiff > 0) { // divide by 0 exception
    $aveCon = round(($rowNewest['con'] - $rowOldest['con'])*3600*1000 / $zeitDiff); // kWh compared to seconds
    $aveGen = round(($rowNewest['gen'] - $rowOldest['gen'])*3600*1000 / $zeitDiff);
  } else { 
    $aveCon = 0.0;
    $aveGen = 0.0;
  }
  
  $zeitString = $zeitNewest->format('Y-m-d H:i');
  if (date('Y-m-d') === $zeitNewest->format('Y-m-d')) { // same day
    $zeitString = $zeitNewest->format('H:i');
  }
  // COLORS: con: red "text-red-500" = rgb(239 68 68); generation: green "text-green-600" = rgb(22 163 74);
  echo '<div class="flex">
    <div class="flex-auto text-left"><b><span class="text-green-600">'.$newestGen.'W</span> / <span class="text-red-500">'.$newestCon.'W</span></b></div>
    <div class="flex-auto text-center">'.$zeitString.'</div>
    <div class="flex-auto text-right">Ø: <b><span class="text-green-600">'.$aveGen.'W</span> / <span class="text-red-500">'.$aveCon.'W</span></b></div>
  </div>
  ';

  if ($queryCount >= $GRAPH_LIMIT) {   
    $axis_x = ''; // rightmost value comes first. Remove something again after the while loop
    $val_yr_con_kwh = '';
    $val_yr_gen_kwh = '';
    $val_yr_cost = '';
    $val_yl_con_ave = '';
    $val_yl_gen_ave = '';
    $val_yl_con = '';
    $val_yl_gen = '';
    
    while ($row = $result->fetch_assoc()) { // did already fetch the newest one. At least 2 remaining  
      if ($row['zeitDiff'] > 0) { // divide by 0 exception
        // 0 in log will not be displayed correctly... values smaller than 10 will not be displayed (empty space ' ')
        $tmp = round($row['conDiff']*3600*1000 / $row['zeitDiff']);
        $watt = ( $tmp > 10 ) ? $tmp : ' ';
        $tmp = round($row['genDiff']*3600*1000 / $row['zeitDiff']);
        $gen = ($tmp > 10 ) ? $tmp : ' ';
      } else { 
        $watt = 10.0;
        $gen = 10.0;
      }
      
      // revert the ordering
      $axis_x = 'new Date("'.$row['zeit'].'"), '.$axis_x; // new Date("2020-03-01 12:00:12")
      $val_yr_con_kwh = ($row['con'] - $rowOldest['con']) .', '.$val_yr_con_kwh; // to get a relative value (and not some huge numbers)
      $val_yr_gen_kwh = ($row['gen'] - $rowOldest['gen']) .', '.$val_yr_gen_kwh;
      $val_yr_cost = -1.0 * 
                    ((($row['con'] - $rowOldest['con'])*$rowOldest['conRate']) - // TODO
                     (($row['gen'] - $rowOldest['gen'])*$rowOldest['genRate'])) .', '.$val_yr_cost;
      $val_yl_con_ave = $aveCon.', '.$val_yl_con_ave;
      $val_yl_gen_ave = $aveGen.', '.$val_yl_gen_ave;
      $val_yl_con = $watt.', '.$val_yl_con;
      $val_yl_gen = $gen.', '.$val_yl_gen;
    } // while
    // remove the last two caracters (a comma-space) and add the brackets before and after
    $axis_x = '[ '.substr($axis_x, 0, -2).' ]';
    $val_yr_con_kwh = '[ '.substr($val_yr_con_kwh, 0, -2).' ]';
    $val_yr_gen_kwh = '[ '.substr($val_yr_gen_kwh, 0, -2).' ]';
    $val_yr_cost = '[ '.substr($val_yr_cost, 0, -2).' ]';
    $val_yl_con_ave = '[ '.substr($val_yl_con_ave, 0, -2).' ]';
    $val_yl_gen_ave = '[ '.substr($val_yl_gen_ave, 0, -2).' ]';
    $val_yl_con = '[ '.substr($val_yl_con, 0, -2).' ]';
    $val_yl_gen = '[ '.substr($val_yl_gen, 0, -2).' ]';
    
    if ($timeSelected === 1) {
      $timeUnit = 'unit: "hour"';
    } elseif ($timeSelected === 7) {
      $timeUnit = 'unit: "day"';
    } else {
      $timeUnit = 'unit: "week"';
    }
    echo '
    <canvas id="myChart" width="600" height="300" class="mb-2"></canvas>
    <script>
    const ctx = document.getElementById("myChart");
    const labels = '.$axis_x.';
    const data = {
      labels: labels,
      datasets: [{
        label: "Verbrauch total [kWh]",
        data: '.$val_yr_con_kwh.',
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
        data: '.$val_yl_con_ave.',
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
        data: '.$val_yl_con.',
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
          x: { 
            type: "time",
            time: { '.$timeUnit.' }
          },
          yleft: { type: "logarithmic", position: "left", ticks: {color: "rgb(25, 99, 132)"} },
          yright: { type: "linear",  position: "right", ticks: {color: "rgba(25, 99, 132, 0.6)"}, grid: {drawOnChartArea: false} }
        }
      }
    };
    const myChart = new Chart( document.getElementById("myChart"), config );
    </script>
    <hr>';
    
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
    <div class="flex-auto text-right"><b><span class="'.$costClass.'">'. number_format((float)$costTotal, 2, '.', '').'</span></b></div>
    </div>      
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
    },
    {
        data: '.$val_yl_con.',
        yAxisID: "yleftCost",
        backgroundColor: "rgba(255,255,255,0.0)",
        borderColor: "rgba(255,255,255,0.0)",
        showLine: false
    },
    {
        data: '.$val_yl_gen.',
        yAxisID: "yleftCost",
        backgroundColor: "rgba(255,255,255,0.0)",
        borderColor: "rgba(255,255,255,0.0)",
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
        x: { 
            type: "time", 
            time: { '.$timeUnit.' },
            ticks: { display: false }
        },
        yleftCost: { type: "logarithmic", position: "left", ticks: {color: "rgba(255, 255, 255, 0.01)"}, grid: {display: false } },
        yrightCost: { type: "linear",  position: "right", ticks: {color: "rgb(0, 0, 0)"} }
        }
    }
    };
    const myChartCost = new Chart( document.getElementById("myChartCost"), configCost );
    </script>';
  } else {
    echo '<br><br> - weniger als '.$GRAPH_LIMIT.' Einträge - <br><br><br>';
  }    
} else {
  echo '<br><br> - noch keine Einträge - <br><br><br>';
}

echo '
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
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Week,  param:Param::con, goBack:safeIntFromExt('GET','goBackWcon', 2), isIndexPage:TRUE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Month, param:Param::con, goBack:safeIntFromExt('GET','goBackMcon', 2), isIndexPage:TRUE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Year,  param:Param::con, goBack:safeIntFromExt('GET','goBackYcon', 2), isIndexPage:TRUE);
echo '<p>Weitere Auswertungen findest du auf der<a href="statistic.php" class="font-medium text-blue-600 hover:text-blue-700">'.getSvg(whichSvg:Svg::ArrowRight, classString:'w-6 h-6 inline').'Statistikseite</a></p>';

?>
<br><br>
</div></body></html>
