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

$weeksPast = safeIntFromExt(source:'GET', varName:'weeksPast', length:2); // 
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

// for some entries, this sql will return the sum of only one line (thin = 24), for others 24 and for the newest ones it returns the sum of lots of entries 
$val_y = '';

$minWatt = 10000;
$maxWatt = 0;

for ($i = 0; $i < 7; $i++) {
  $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
  $sql = $sql. ' WHERE `userid` = "'.$userid.'" AND `zeit` > "'.$dailyStrings[$i].'" AND `zeit` < "'.$dailyStrings[$i+1].'";';
  // echo $sql."<br />";
  $result = $dbConn->query($sql); // returns only one row
  $row = $result->fetch_assoc();
  
  if ($row['sumZeitDiff'] > 0) { // divide by 0 exception
    $watt = max(round($row['sumConsDiff']*3600*1000 / $row['sumZeitDiff']), 10.0); // max(val,10.0) because 0 in log will not be displayed correctly. 10 to save a 'decade' in range
    $minWatt = min($minWatt, $watt);
    $maxWatt = max($maxWatt, $watt);
  } else { 
    $watt = ' '; 
  }      
  $val_y .= $watt.', ';
}
$minWatt = max(0, $minWatt - 100); // make sure it's not negative
$maxWatt = $maxWatt + 100;


$lastOrThis = ($weeksPast === 0) ? 'diese' : 'letzte';
$lastWkLnk = ($weeksPast === 0) ? '?weeksPast=1">letzte' : '">diese'; // TODO: clickable triangels to scroll through the weeks

echo '<hr>
<div class="grid grid-cols-2 justify-items-start">
  <div class="justify-self-center">Tagesverbrauch '.$lastOrThis.' Woche</div>
  <div class="justify-self-end"><a class="underline" href="statistic.php'.$lastWkLnk.' Woche</a></div>
</div>
<hr>';

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
        min: '.$minWatt.',
        max: '.$maxWatt.'
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
