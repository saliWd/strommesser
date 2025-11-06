<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

// returns the time range to be displayed as int. Possible values are: 1 (for last 1 hour), 6, 24, 25. 25 means: all data
function getTimeRange():int {
  $returnVal = 7;  // default time range
  $unsafeInt = safeIntFromExt(source:'GET',varName:'range',length:3);
  if (($unsafeInt === 1) or ($unsafeInt === 7) or ($unsafeInt === 30) or ($unsafeInt === 365)) {
    $returnVal = $unsafeInt; 
  }
  return $returnVal;
}

$timeSelected = getTimeRange();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$resultCnt = $dbConn->query('SELECT COUNT(*) as `total` FROM `pico_log` WHERE `userid` = "'.$userid.'" LIMIT 1;'); // guaranteed to return one row
$resultFreshest = $dbConn->query('SELECT `zeit` FROM `pico_log` WHERE `userid` = "'.$userid.'" ORDER BY `zeit` DESC LIMIT 1;'); // cannot combine those two

$rowCnt = $resultCnt->fetch_assoc(); // returns one row only
$rowFreshest = $resultFreshest->fetch_assoc(); // returns 0 or 1 row
$totalCount = $rowCnt['total'];

printBeginOfPage_v2(site:'status_loopCount.php');

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
            <a href="status_loopCount.php?range='.$tabText[0].'" class="inline-block p-4 border-b-2 rounded-t-lg '.$tabText[2].'">'.$tabText[1].'</a>
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

  $sql = 'SELECT `loopCount`, `zeit` from `pico_log` WHERE `userid` = "'.$userid.'" AND `zeit` > "'.$zeitOldestString.'" ';
  $sql .= 'ORDER BY `zeit` DESC LIMIT '.$QUERY_LIMIT.';';

  $result = $dbConn->query($sql);
  $result->data_seek($result->num_rows - 1); // skip to the last entry of the rows
  $rowOldest = $result->fetch_assoc();
  $result->data_seek(0); // go back to the first row

  $rowNewest = $result->fetch_assoc();
  $queryCount = $result->num_rows; // this may be < graph-limit ( = display at least the newest) or >= graph-limit ( = all good)
 
  $zeitString = $zeitNewest->format('Y-m-d H:i');
  if (date('Y-m-d') === $zeitNewest->format('Y-m-d')) { // same day
    $zeitString = $zeitNewest->format('H:i');
  }
  // COLORS: cons: red "text-red-500" = rgb(239 68 68); generation: green "text-green-600" = rgb(22 163 74);
  echo '<div class="flex">
    <div class="flex-auto text-left"><b>Latest loopCount: '.$rowNewest['loopCount'].'</b></div>
    <div class="flex-auto text-center">'.$zeitString.'</div>
    <div class="flex-auto text-right">&nbsp;</div>
  </div>
  ';

  if ($queryCount >= $GRAPH_LIMIT) {   
    $axis_x = ''; // rightmost value comes first. Remove something again after the while loop
    $val_y_loopCount = '';
    
    while ($row = $result->fetch_assoc()) { // did already fetch the newest one. At least 2 remaining       
      // revert the ordering
      $axis_x = 'new Date("'.$row['zeit'].'"), '.$axis_x; // new Date("2020-03-01 12:00:12")
      $val_y_loopCount = $row['loopCount'].', '.$val_y_loopCount; // to get a relative value (and not some huge numbers)      
    } // while
    // remove the last two caracters (a comma-space) and add the brackets before and after
    $axis_x = '[ '.substr($axis_x, 0, -2).' ]';
    $val_y_loopCount = '[ '.substr($val_y_loopCount, 0, -2).' ]';
    
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
        label: "Counter value",
        data: '.$val_y_loopCount.',        
        backgroundColor: "rgba(239, 68, 68, 0.2)",
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
          y: { type: "linear", ticks: {color: "rgb(25, 99, 132)"} }
        }
      }
    };
    const myChart = new Chart( document.getElementById("myChart"), config );
    </script>
    <hr>';
    
    
  } else {
    echo '<br><br> - weniger als '.$GRAPH_LIMIT.' Einträge - <br><br><br>';
  }    
} else {
  echo '<br><br> - noch keine Einträge - <br><br><br>';
}

echo '
<div class="flex items-center">
  <div>&nbsp;</div>
  <div class="flex-auto text-right">Insgesamt '.$totalCount.' Einträge</div>
</div>
<br>';

?>
<br><br>
</div></body></html>
