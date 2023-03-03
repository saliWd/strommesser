<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$LIMIT_LED_MAX_VALUE = 2000;
$LIMIT_LED_BRIGHTNESS = 255;

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: export all user data
// do = 2: process setting changes

if ($doSafe === 0) { // entry point of this site
  printBeginOfPage(site:'settings.php', title:'');
  $result = $dbConn->query('SELECT `ledMaxValue`,`ledBrightness` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
  $row = $result->fetch_assoc();
  
  echo '
  <nav class="p-3 border-gray-200 rounded bg-gray-50">
    <div class="container flex flex-wrap items-center justify-between mx-auto">
      <a href="#" class="flex items-center">
        <img src="img/messer_200.png" class="h-6 mr-3 sm:h-10" alt="StromMesser Logo" />
        <span class="self-center text-2xl font-semibold whitespace-nowrap">Einstellungen</span>
      </a>
      <button data-collapse-toggle="navbar-solid-bg" type="button" class="inline-flex items-center p-2 ml-3 text-sm text-gray-500 rounded-lg md:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200" aria-controls="navbar-solid-bg" aria-expanded="false">
        <span class="sr-only">in page menu</span>
        <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path></svg>
      </button>
      <div class="hidden w-full md:block md:w-auto" id="navbar-solid-bg">
        <ul class="flex flex-col mt-4 rounded-lg bg-gray-50 md:flex-row md:space-x-8 md:mt-0 md:text-sm md:font-medium md:border-0 md:bg-transparent">        
          <li>
            <a href="#anchorMiniDisplay" class="block py-2 pl-3 pr-4 text-gray-700 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0">Mini-Display</a>
          </li>
          <li>
            <a href="#anchorUserAccount" class="block py-2 pl-3 pr-4 text-gray-700 rounded hover:bg-gray-100 md:hover:bg-transparent md:border-0 md:hover:text-blue-700 md:p-0">Benutzereinstellungen</a>
          </li>            
        </ul>
      </div>
    </div>
  </nav>
  <div id="anchorMiniDisplay" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Mini-Display</h3>
    <img class="w-48 mx-auto" src="img/display.jpg" alt="Anzeige Stromverbrauch. Mit einem kleinen, stromsparenden Bildschirm und gut sichtbarer LED">
    <p>&nbsp;</p>
    <form id="settingsValues" action="settings.php?do=2" method="post">
      <p class="text-left"><b>Maximalwert Farbskala:</b><br>
      LED und Minibildschirm zeigen den aktuellen Verbrauch mit einer Farbskala von blau über grün nach gelb und schlussendlich rot.<br>
      0 Watt entspricht der Farbe blau, der Maximalwert (und alles darüber) wird rot angezeigt.</p>
      <p class="mx-auto"><input id="ledMaxValue" name="ledMaxValue" type="range" min="100" max="'.$LIMIT_LED_MAX_VALUE.'" step="20" value="'.$row['ledMaxValue'].'" class="range" oninput="this.nextElementSibling.value=this.value"> <output>'.$row['ledMaxValue'].'</output>W</p>
      <hr>
      <p class="text-left"><b>LED Helligkeit:</b><br>
      Die Helligkeit der farbigen LED. Von 0 (ausgeschaltet) bis 255.<br>
      In der Nacht (22 Uhr bis 6 Uhr) leuchtet sie übrigens 50% dunkler.</p>
      <p class="mx-auto"><input id="ledBrightness" name="ledBrightness" type="range" min="0" max="'.$LIMIT_LED_BRIGHTNESS.'" step="5" value="'.$row['ledBrightness'].'" class="range" oninput="this.nextElementSibling.value=this.value"> <output>'.$row['ledBrightness'].'</output></p>
      <p class="mx-auto"><input id="settingsFormSubmit" class="mt-8 input-text mx-auto" name="settingsFormSubmit" type="submit" value="speichern"></p>
    </form>
  </div>
  '.getHr().'
  <div id="anchorUserAccount" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Benutzereinstellungen</h3>
    <div class="flex flex-row justify-center">
      <div><a href="login.php?do=3" class="input-text basis-full">Passwort ändern</a></div>
    </div>
    <hr class="my-4">
    <h4 class="mb-2 text-l font-bold tracking-tight text-gray-900">Daten exportieren</h4>
    <p>Deine Messdaten werden im CSV-Format unter `verbrauch.csv` gespeichert</p>
    <br>
    <div class="flex flex-row justify-center">
      <div><a href="settings.php?do=1" class="input-text basis-full w-96">Alle Messdaten als csv herunterladen</a></div>
    </div>
  </div>
  <br><br><br><br><br><br>
  ';
} elseif ($doSafe === 1) { // export all entries
  header("Content-Type: application/octet-stream");
  header("Content-Transfer-Encoding: Binary");
  header("Content-disposition: attachment; filename=\"verbrauch.csv\"");
 
  $outFile = fopen("php://output", "w");
  fputcsv($outFile, array('id', 'userid', 'consumption', 'consDiff', 'zeit', 'zeitDiff', 'thin'));
  $result = $dbConn->query('SELECT * FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id`;');
  while ($row = $result->fetch_row()) { 
    fputcsv($outFile, $row); 
  }
  fclose($outFile);
  exit();
} elseif ($doSafe === 2) {
  printBeginOfPage(site:'settings.php', title:'Einstellungen');
  $ledMaxValue = safeIntFromExt(source:'POST',varName:'ledMaxValue',length:4);  
  $ledBrightness = safeIntFromExt(source:'POST',varName:'ledBrightness',length:3);
  $ledMaxValue = limitInt(input:$ledMaxValue,lower:0,upper:$LIMIT_LED_MAX_VALUE);
  $ledBrightness = limitInt(input:$ledBrightness,lower:0,upper:$LIMIT_LED_BRIGHTNESS);

  $result = $dbConn->query('UPDATE `kunden` SET `ledMaxValue` = "'.$ledMaxValue.'", `ledBrightness` = "'.$ledBrightness.'" WHERE `id` = "'.$userid.'";');

  echo 'gespeichert<br>';
  echo '<script>setTimeout(() => { window.location.href = \'settings.php\'; }, 2000);</script>';
} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
</div></body></html>
