<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

// shows several bar graphs:
// - daily consumption this week (starting monday), consumption last week
// - daily consumption this month (starting 1st of), consumption last month
// - weekly consumption this year
// TODO: always: scrollable (select this month or go back to last month etc.)
// always: displaying the data as I have it. If only two days this month, I display those...

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)
echo '<!DOCTYPE html>
  <html>
  <head>
  <meta charset="utf-8">
  <title>StromMesser Trial</title>
  <meta name="description" content="zeigt deinen Energieverbrauch">  
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="strommesser.css" type="text/css">
  <script src="script/chart.min.js"></script>
  <script src="script/moment.min.mine.js"></script>
  <script src="script/chartjs-adapter-moment.mine.js"></script>
  <script src="script/flowbite.min.js"></script>
  </head>
  <body>
  ';
  printNavMenu_v2('statistic.php');
  echo '
  <div class="container mx-auto px-4 py-2 lg text-center" id="anchorTopOfPage">
  <br><br>
<div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Tageswerte pro Woche</h3>
  <p class="font-normal text-gray-700">Für jeden Wochentag ist der Durchschnittsverbrauch in Watt dargestellt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh.</p>
  <p class="font-normal text-gray-700">Gemessen wird von 00:00 bis 23:59 bzw. am aktuellen Tag von 00:00 bis `jetzt`.</p>
  <p class="font-normal text-gray-700">Mit den Navigationspfeilen kannst du zwischen den Wochen blättern (TODO: noch in Arbeit)</p>
</div>
';
printBarGraph(
  values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:0), 
  chartId:'WeeklyNow', 
  title:'diese Woche',
  isIndexPage:FALSE
);
printBarGraph(
  values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:1), 
  chartId:'WeeklyLast', 
  title:'letzte Woche',
  isIndexPage:FALSE
);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Tageswerte pro Monat</h3>
  <p class="font-normal text-gray-700">Für jeden Tag ist der Durchschnittsverbrauch in Watt dargestellt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh.</p>
  <p class="font-normal text-gray-700">Gemessen wird von 00:00 bis 23:59 bzw. am aktuellen Tag von 00:00 bis `jetzt`.</p>
  <p class="font-normal text-gray-700">Mit den Navigationspfeilen kannst du zwischen den Monaten blättern (TODO: noch in Arbeit)</p>
</div>
';
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:0), chartId:'MonthlyNow', title:'diesen Monat');
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:1), chartId:'MonthlyLast', title:'letzten Monat');

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Wochenwerte übers Jahr</h3>
  <p class="font-normal text-gray-700">Für jede Woche ist der Durchschnittsverbrauch in Watt dargestellt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Wochenverbrauch von 168 kWh.</p>
  <p class="font-normal text-gray-700">Gemessen wird von Montag 00:00 bis Sonntag 23:59 bzw. in der aktuellen Woche von Montag 00:00 bis `jetzt`.</p>
</div>
';
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Year, goBack:0), chartId:'YearlyNow', title:'dieses Jahr');
?>
</div></body></html>
