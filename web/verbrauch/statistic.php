<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();


// shows several graphs: 
// - consumption in this week (starting monday), consumption last week
// - consumption in this month (starting 1st of)
// TODO: - consumption per week (starting 1st week of year, always Monday to Sunday)
// TODO: always: scrollable (select this month or go back to last month etc.)
// always: displaying the data as I have it. If only two days this month, I display those...
// bar graph for few items

// returns the time range to be displayed as int. Possible values are: 1 (for last 1 hour), 6, 24, 25. 25 means: all data
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)
printBeginOfPage(enableReload:FALSE, timerange:'', site:'statistic.php', title:'Statistiken');

echo '
<div class="flex items-center">
  <a href="#anchorWeeklyNow" class="flex-auto underline">Diese Woche</a>
  <a href="#anchorWeeklyLast" class="flex-auto underline">Letzte Woche</a>
  <a href="#anchorMonthlyNow" class="flex-auto underline">Diesen Monat</a>
</div><br><br>';
$val_y = getWeeklyValues(dbConn:$dbConn, weeksPast:0, userid:$userid);
printWeeklyGraph (val_y:$val_y, chartId:'WeeklyNow', title:'diese');

$val_y = getWeeklyValues(dbConn:$dbConn, weeksPast:1, userid:$userid);
printWeeklyGraph (val_y:$val_y, chartId:'WeeklyLast', title:'letzte');

[$val_x, $val_y] = getMonthlyValues(dbConn:$dbConn, userid:$userid);
printMonthlyGraph (val_x:$val_x, val_y:$val_y, chartId:'MonthlyNow');

?>
</div></body></html>
