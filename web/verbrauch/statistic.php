<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();


// shows several bar graphs:
// - daily consumption this week (starting monday), consumption last week
// - daily consumption this month (starting 1st of), consumption last month
// - weekly consumption this year
// TODO: always: scrollable (select this month or go back to last month etc.)
// always: displaying the data as I have it. If only two days this month, I display those...
// bar graph for few items

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)
printBeginOfPage(site:'statistic.php', title:'Statistiken');

// TODO: this is ugly on desktop layout (okayish on mobile)
echo '
<div class="grid grid-cols-2 gap-4 mt-8">
  <a href="#anchorWeeklyNow" class="flex-auto underline">Diese Woche</a>
  <a href="#anchorWeeklyLast" class="flex-auto underline">Letzte Woche</a>
  <a href="#anchorMonthlyNow" class="flex-auto underline">Diesen Monat</a>
  <a href="#anchorMonthlyLast" class="flex-auto underline">Letzten Monat</a>
  <a href="#anchorYearlyLast" class="flex-auto underline">Dieses Jahr</a>
</div><br><br>';

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:0), chartId:'WeeklyNow', title:'diese Woche');
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:1), chartId:'WeeklyLast', title:'letzte Woche');

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:0), chartId:'MonthlyNow', title:'diesen Monat');
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:1), chartId:'MonthlyLast', title:'letzten Monat');

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Year, goBack:0), chartId:'YearlyNow', title:'dieses Jahr');
?>
</div></body></html>
