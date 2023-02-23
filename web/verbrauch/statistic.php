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

echo '
<nav class="p-3 border-gray-200 rounded bg-gray-50">
  <div class="container flex flex-wrap items-center justify-between mx-auto">
    <a href="#" class="flex items-center">
        <img src="img/messer_200.png" class="h-6 mr-3 sm:h-10" alt="StromMesser Logo" />
        <span class="self-center text-xl font-semibold whitespace-nowrap">Statistiken</span>
    </a>
    <button data-collapse-toggle="navbar-solid-bg" type="button" class="inline-flex items-center p-2 ml-3 text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-controls="navbar-solid-bg" aria-expanded="false">
      <span class="sr-only">in page menu</span>
      <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
    </button>
    <div class="hidden w-full md:block md:w-auto" id="navbar-solid-bg">
      <ul class="flex flex-col mt-4 rounded-lg bg-gray-50 md:flex-row md:space-x-8 md:mt-0 md:text-sm md:font-medium md:border-0 md:bg-transparent">        
        <li>
          <a href="#anchorWeeklyNow" class="block py-2 pl-3 pr-4 text-gray-700 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0">Wöchentlich</a>
        </li>
        <li>
          <a href="#anchorMonthlyNow" class="block py-2 pl-3 pr-4 text-gray-700 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0">Monatlich</a>
        </li>
        <li>
          <a href="#anchorYearlyNow" class="block py-2 pl-3 pr-4 text-gray-700 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0">Jährlich</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<br><br>';

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:0), chartId:'WeeklyNow', title:'diese Woche');
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, goBack:1), chartId:'WeeklyLast', title:'letzte Woche');

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:0), chartId:'MonthlyNow', title:'diesen Monat');
printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, goBack:1), chartId:'MonthlyLast', title:'letzten Monat');

printBarGraph(values:getValues(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Year, goBack:0), chartId:'YearlyNow', title:'dieses Jahr');
?>
</div></body></html>
