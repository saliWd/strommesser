<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

// shows several graphs: 
// - consumption in this week (starting monday)
// - consumption in this month (starting 1st of)
// - consumption per week (starting 1st week of year, always Monday to Sunday)
// always: scrollable (select this month or go back to last month etc.)
// always: day accurate (last hours do not count)
// always: displaying the data as I have it. If only two days this month, I display those...
// bar graph for few items

// returns the time range to be displayed as int. Possible values are: 1 (for last 1 hour), 6, 24, 25. 25 means: all data
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)
printBeginOfPage(enableReload:FALSE, timerange:'', site:'statistic.php');

// this week: only have the daily consumption up to 3 days ago. Need to calculate the last days here (not yet in data base)

// find the monday in this week
$monday = date_create("last Monday 00:00");
$mondayString = $monday->format('Y-m-d 00:00:00');

$sqlUser = ' from `verbrauch` WHERE `userid` = "'.$userid.'" ';

$sql = 'SELECT `consDiff`, `zeitDiff`'.$sqlUser.'AND `zeit` >= "'.$mondayString.'" AND `thin` = "24" ';
$sql .= 'ORDER BY `id` LIMIT 7;';    // TODO: add the non-daily data as well

$result = $dbConn->query($sql);
echo '<hr>Tagesverbrauch diese Woche<hr>';

$val_y = '';
    
while ($row = $result->fetch_assoc()) {
  if ($row['zeitDiff'] > 0) { // divide by 0 exception
    $watt = max(round($row['consDiff']*3600*1000 / $row['zeitDiff']), 10.0); // max(val,10.0) because 0 in log will not be displayed correctly. 10 to save a 'decade' in range
  } else { 
    $watt = 0; 
  }      
  $val_y .= $watt.', ';
} // while 
// remove the last two caracters (a comma-space) and add the brackets before and after
$val_y = '[ '.substr($val_y, 0, -2).' ]';

echo '
<canvas id="myChart" width="600" height="300" class="mb-2"></canvas>
<script>
const ctx = document.getElementById("myChart");
const labels = [ "Mo", "Di", "Mi", "Do", "Fr", "Sa", "So" ];
const data = {
  labels: labels,
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
const config = {
  type: "bar",
  data: data,
  options: {
    scales: {
      y: {
        type: "logarithmic"
      }
    },
    plugins : {
      legend: {
        display: false
      }
    }
  },
};
const myChart = new Chart( document.getElementById("myChart"), config );
</script>';

?>
</div></body></html>