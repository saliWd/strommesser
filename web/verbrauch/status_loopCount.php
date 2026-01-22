<?php declare(strict_types=1); 
require_once 'functions.php';
$dbConn = initialize();

function doReduce($dbConn, int $userid):bool {
  // TODO: similar code as used in pico2w.py. Use function...
  $sqlNoThin = "`userid` = $userid AND `thin` = 0";
  $formatString = 'Y-m-d H:00:00';
  // search the oldest one where thinnig has not yet been applied (and is older than 25h)
  $sql = "SELECT `zeit` FROM `pico_log` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL 25 HOUR) ORDER BY `id` ASC LIMIT 1;";
  $result = $dbConn->query($sql);
  if ($result->num_rows < 1) { // if there is no entry older than 25h, there is nothing to do. NB: there is a difference between NOW and last-insert-time
    return false;
  }
  $row = $result->fetch_assoc();

  // compact all from the last hour before this entry
  $zeit = date_create(datetime: $row['zeit']); // e.g. 18:43
  $zeitAligned = date_create(datetime: $zeit->format(format: $formatString)); // start of the last hour, e.g. 18:00
  $zeitAlignedStr = $zeitAligned->format(format:$formatString); // as string: 19:00
  $zeitAlignedPlus = $zeitAligned->modify(modifier:'+1 hour'); // go one hour/day further, 19:00
  $zeitAlignedPlusStr = $zeitAlignedPlus->format(format: $formatString); // as string: 19:00
  
  // check whether this one is still old enough and thinning is ok
  $sql = "SELECT `id` FROM `pico_log` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL 25 HOUR)";
  $sql .= " AND `zeit` >= \"$zeitAlignedPlusStr\"";
  $sql .= " ORDER BY `id` ASC LIMIT 1;";
  $result = $dbConn->query($sql);
  if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
    return false;
  }

  $sql = "SELECT `id` FROM `pico_log` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL 25 HOUR)";
  $sql .= " AND `zeit` < \"$zeitAlignedPlusStr\" AND `zeit` >= \"$zeitAlignedStr\"";
  $sql .= " ORDER BY `id` DESC LIMIT 1;"; // NB: this is ASC in the reduction code from pico2w_v4. I'm interested in the last one, not the first
  $result = $dbConn->query($sql);
  if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
    return false;
  }

  $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
  $idToUpdate = $row['id']; // oldest one
  
  // now do the update and then delete the others
  $result = $dbConn->query("UPDATE `pico_log` SET `thin` = \"1\" WHERE `id` = \"$idToUpdate\";");
  $result = $dbConn->query("DELETE FROM `pico_log` WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";");
  return true;
}

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$resultCnt = $dbConn->query(query:"SELECT COUNT(*) as `total` FROM `pico_log` WHERE `userid` = \"$userid\" LIMIT 1;"); // guaranteed to return one row
$resultFreshest = $dbConn->query(query:"SELECT `zeit` FROM `pico_log` WHERE `userid` = \"$userid\" ORDER BY `zeit` DESC LIMIT 1;"); // cannot combine those two

$rowCnt = $resultCnt->fetch_assoc(); // returns one row only
$rowFreshest = $resultFreshest->fetch_assoc(); // returns 0 or 1 row
$totalCount = $rowCnt['total'];

printBeginOfPage_v2(site:'status_loopCount.php');
do {
  $didReduce = doReduce(dbConn:$dbConn, userid:$userid); 
} while ($didReduce);

if ($totalCount > 0) {// this may be 0
  $zeitNewest = date_create($rowFreshest['zeit']);    
  $zeitOldest = date_create($rowFreshest['zeit']);
  $zeitOldest->modify(modifier: '-365 days');
  $zeitOldestString = $zeitOldest->format('Y-m-d H:i:s');
  

  $QUERY_LIMIT = 10000; // have some upper limit, both for js and db-performance
  $GRAPH_LIMIT = 3; // does not make sense to display a graph otherwise

  $sql = "SELECT `loopCount`, `zeit` from `pico_log` WHERE `userid` = \"$userid\" AND `zeit` > \"$zeitOldestString\" ";
  $sql .= "ORDER BY `zeit` DESC LIMIT $QUERY_LIMIT;";

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
  echo '
  <div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Status LoopCounter</h3>
    <p class="font-normal text-gray-700">
    Der loop counter wird alle 5 Sekunden raufgezählt. Unten siehst du, ob das für deinen Anschluss in den letzten 365 Tagen zuverlässig funktioniert hat.<br>
    Wenn der counter wieder bei 0 startet, hat sich der Mikrocontroller am Display neu gestartet. Solange das nur `selten` passiert, ist das soweit kein Problem...
    </p>
  </div>
  <br>
  <div class="flex">
    <div class="flex-auto text-left"><b>Aktueller LoopCount: '.$rowNewest['loopCount'].'</b></div>
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
            time: { unit: "week" }
          },
          y: { type: "linear", ticks: {color: "rgb(25, 99, 132)"} }
        }
      }
    };
    const myChart = new Chart( document.getElementById("myChart"), config );
    </script>
    <hr>';   
  } else {
    echo "<br><br> - weniger als $GRAPH_LIMIT Einträge - <br><br><br>";
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
