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
  $result = $dbConn->query('SELECT `ledMaxValue`,`ledBrightness` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
  $row = $result->fetch_assoc();
  
  printBeginOfPage_v2(site:'settings.php');
  echo '
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
  </div>
  '.getHr().'
  <div id="anchorDataExport" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Daten exportieren</h3>
    <p>Deine Messdaten werden im CSV-Format unter `verbrauch.csv` gespeichert</p>
    <br>
    <div class="flex flex-row justify-center">
      <div><a href="settings.php?do=1" class="input-text basis-full w-96">Alle Messdaten als csv herunterladen</a></div>
    </div>
  </div>
  <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
  ';
} elseif ($doSafe === 1) { // export all entries
  header("Content-Type: application/octet-stream");
  header("Content-Transfer-Encoding: Binary");
  header("Content-disposition: attachment; filename=\"verbrauch.csv\"");
 
  $outFile = fopen("php://output", "w");
  /*
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `consumption` decimal(10,3) NOT NULL,
  `consDiff` decimal(10,3) NOT NULL,
  `consNt` decimal(10,3) NOT NULL,
  `consNtDiff` decimal(10,3) NOT NULL,
  `consHt` decimal(10,3) NOT NULL,
  `consHtDiff` decimal(10,3) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `genNt` decimal(10,3) NOT NULL,
  `genNtDiff` decimal(10,3) NOT NULL,
  `genHt` decimal(10,3) NOT NULL,
  `genHtDiff` decimal(10,3) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  */
  fputcsv($outFile, array('id','userid','consumption','consDiff','consNt','consNtDiff','consHt','consHtDiff','gen','genDiff','genNt','genNtDiff','genHt','genHtDiff','zeit','zeitDiff'));
  $result = $dbConn->query('SELECT * FROM `verbrauchArchive` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 24000;'); // limit 24k = bit more than one month. To limit file size
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
