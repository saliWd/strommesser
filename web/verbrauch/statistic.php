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
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Verbrauch pro Woche<span></div>  
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, param:EnumParam::cons, goBack:safeIntFromExt('GET','goBackW', 2), isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Verbrauch NT pro Woche<span></div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, param:EnumParam::consNt, goBack:safeIntFromExt('GET','goBackW', 2), isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Verbrauch HT pro Woche<span></div>  
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Week, param:EnumParam::consHt, goBack:safeIntFromExt('GET','goBackW', 2), isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Tageswerte pro Monat<span></div>
  <div><button class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center" type="button" data-modal-toggle="modal-m">Erklärungen anzeigen</button></div>  
</div>
<div id="modal-m" tabindex="-1" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 h-modal">
  <div class="relative p-4 w-full h-full">
      <div class="relative bg-white rounded-lg shadow">
          <div class="p-6 text-left">
            <h3 class="mb-4 text-xl font-bold tracking-tight text-gray-900">Tageswerte pro Monat</h3>
            <p class="font-normal text-gray-700">Für jeden Tag ist der Durchschnittsverbrauch in Watt dargestellt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Tagesverbrauch von 24 kWh.</p>
            <p class="font-normal text-gray-700">Gemessen wird von 00:00 bis 23:59 bzw. am aktuellen Tag von 00:00 bis `jetzt`.</p>
            <p class="font-normal text-gray-700">Der Durchschnitt über den ganzen Monat ist gestrichelt eingezeichnet.</p>
            <p class="font-normal text-gray-700 mb-4">Mit den Navigationspfeilen kannst du zwischen den Monaten blättern.</p>
            <button data-modal-toggle="modal-m" type="button" class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
              weniger
            </button>
          </div>
      </div>
  </div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Month, param:EnumParam::cons, goBack:safeIntFromExt('GET','goBackM', 2), isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Wochenwerte übers Jahr<span></div>
  <div><button class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center" type="button" data-modal-toggle="modal-y">Erklärungen anzeigen</button></div>  
</div>
<div id="modal-y" tabindex="-1" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 h-modal">
  <div class="relative p-4 w-full h-full">
      <div class="relative bg-white rounded-lg shadow">
          <div class="p-6 text-left">
            <h3 class="mb-4 text-xl font-bold tracking-tight text-gray-900">Wochenwerte übers Jahr</h3>
            <p class="font-normal text-gray-700">Für jede Woche ist der Durchschnittsverbrauch in Watt dargestellt. Ein Durschnittsverbrauch von 1000 Watt enstpricht einem Wochenverbrauch von 168 kWh.</p>
            <p class="font-normal text-gray-700">Gemessen wird von Montag 00:00 bis Sonntag 23:59 bzw. in der aktuellen Woche von Montag 00:00 bis `jetzt`.</p>
            <p class="font-normal text-gray-700 mb-4">Der Durchschnitt über das ganze Jahr ist gestrichelt eingezeichnet.</p>
            <button data-modal-toggle="modal-y" type="button" class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
              weniger
            </button>
          </div>
      </div>
  </div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Year, param:EnumParam::cons, goBack:safeIntFromExt('GET','goBackY', 2), isIndexPage:FALSE);

echo '
<div class="text-left mt-4 block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100 flex"> 
  <div class="flex-auto"><span class="mb-2 text-xl font-bold tracking-tight text-gray-900">Wochenwerte übers Jahr (Produktion)<span></div>
  <div><button class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center" type="button" data-modal-toggle="modal-y-gen">Erklärungen anzeigen</button></div>  
</div>
<div id="modal-y-gen" tabindex="-1" class="hidden overflow-y-auto overflow-x-hidden fixed top-0 right-0 left-0 z-50 h-modal">
  <div class="relative p-4 w-full h-full">
      <div class="relative bg-white rounded-lg shadow">
          <div class="p-6 text-left">
            <h3 class="mb-4 text-xl font-bold tracking-tight text-gray-900">Wochenwerte übers Jahr (Produktion)</h3>
            <p class="font-normal text-gray-700">Für jede Woche ist die Durchschnittsproduktion in Watt dargestellt. Eine Durschnittsproduktion von 1000 Watt enstpricht einer Wochenproduktion von 168 kWh.</p>
            <p class="font-normal text-gray-700">Gemessen wird von Montag 00:00 bis Sonntag 23:59 bzw. in der aktuellen Woche von Montag 00:00 bis `jetzt`.</p>
            <p class="font-normal text-gray-700 mb-4">Der Durchschnitt über das ganze Jahr ist gestrichelt eingezeichnet.</p>
            <button data-modal-toggle="modal-y-gen" type="button" class="block text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">
              weniger
            </button>
          </div>
      </div>
  </div>
</div>
';
printBarGraph(dbConn:$dbConn, userid:$userid, timerange:EnumTimerange::Year, param:EnumParam::gen, goBack:safeIntFromExt('GET','goBackY', 2), isIndexPage:FALSE);

?>
</div></body></html>
