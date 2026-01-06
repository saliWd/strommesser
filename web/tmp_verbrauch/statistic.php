<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

// shows several bar graphs:
// - daily consumption this week (starting monday), consumption last week
// - daily consumption this month (starting 1st of), consumption last month
// - weekly consumption this year
// always: scrollable (select this month or go back to last month etc.)
// always: displaying the data as I have it. If only two days this month, I display those...

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)
printBeginOfPage_v2(site:'statistic.php');

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900" id="anchorW">Pro Woche<span></div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Week, param:Param::cost, goBack:safeIntFromExt('GET','goBackWcost', 2),   isIndexPage:FALSE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Week, param:Param::con,  goBack:safeIntFromExt('GET','goBackWcons', 2),   isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900" id="anchorM">Pro Monat<span></div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Month, param:Param::cost, goBack:safeIntFromExt('GET','goBackMcost', 2),   isIndexPage:FALSE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Month, param:Param::con,  goBack:safeIntFromExt('GET','goBackMcons', 2),   isIndexPage:FALSE);
echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900" id="anchorY">Pro Jahr<span></div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Year, param:Param::cost, goBack:safeIntFromExt('GET','goBackYcost', 2),   isIndexPage:FALSE);
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:Timerange::Year, param:Param::con,  goBack:safeIntFromExt('GET','goBackYcons', 2),   isIndexPage:FALSE);

printGraphExplanation(isIndexPage:FALSE);
?>
</div></body></html>
