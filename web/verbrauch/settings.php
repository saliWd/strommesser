<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$LIMIT_LED_MAX_VALUE_CONS = 2000;
$LIMIT_LED_MAX_VAL_GEN = 8000;
$LIMIT_LED_BRIGHTNESS = 255;

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: export all user data
// do = 2: process setting changes
// do = 3: present the 'delete exported data?'
// do = 4: process 'delete archived'
if ($doSafe === 0) { // entry point of this site
  $result = $dbConn->query('SELECT `ledMaxValue`, `ledMaxValGen`, `ledBrightness` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
  $row = $result->fetch_assoc();
  
  printBeginOfPage_v2(site:'settings.php');  
  echo '
  <div id="anchorMiniDisplay" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Mini-Display</h3>
    <img class="w-48 mx-auto" src="img/display.jpg" alt="Anzeige Stromverbrauch. Mit einem kleinen, stromsparenden Bildschirm und gut sichtbarer LED">
    <p>&nbsp;</p>
    <form id="settingsValues" action="settings.php?do=2" method="post">
      <p class="text-left"><b>Maximalwert Farbskala:</b><br>
      LED und Minibildschirm zeigen die aktuelle Leistung mit einer Farbskala von rot über gelb nach grün schlussendlich blau. Blau ist "gut", rot ist "schlecht".<br>
      Verbrauch (Leistung wird aus dem Netz gezogen) wird dementsprechend mit Rottönen angezeigt.<br>
      Beim Einspeisen (Leistung geht ins Netz) pulsiert die LED und die Farben gehen von grün nach blau. Der Maximalwert (plus alles darüber) ist blau.</p>
      <br><br>
      <table>
      <tr>
        <td width="48%" align="right">Verbrauch &nbsp;&nbsp;&nbsp;</td>
        <td width="4%" align="center">0</td>
        <td width="48%" align="left">&nbsp;&nbsp;&nbsp; Einspeisung</td>
      </tr>
      <tr>
        <td colspan="3" align="center"><img class="h-1 w-72" src="img/redToBlue.png" alt="Farbskala Rot-nach-Blau"></td>
      </tr>
      <tr>
        <td width="48%" align="right">-<output>'.$row['ledMaxValue'].'</output>W <input dir="rtl" id="ledMaxValue" name="ledMaxValue" type="range" min="50" max="'.$LIMIT_LED_MAX_VALUE_CONS.'" step="50" value="'.$row['ledMaxValue'].'" class="range" oninput="this.previousElementSibling.value=this.value"></td>
        <td width="4%"></td>
        <td width="48%" align="left"><input id="ledMaxValGen" name="ledMaxValGen" type="range" min="50" max="'.$LIMIT_LED_MAX_VAL_GEN.'" step="50" value="'.$row['ledMaxValGen'].'" class="range" oninput="this.nextElementSibling.value=this.value"> <output>'.$row['ledMaxValGen'].'</output>W</td>
      </tr>      
      </table> 
      <br>
      <hr>
      <p class="text-left"><b>LED Helligkeit:</b><br>
      Die Helligkeit der farbigen LED. Von 0 (ausgeschaltet) bis 255.<br>
      In der Nacht (21 Uhr bis 6 Uhr) leuchtet sie übrigens 75% dunkler.</p>
      <p class="mx-auto"><input id="ledBrightness" name="ledBrightness" type="range" min="0" max="'.$LIMIT_LED_BRIGHTNESS.'" step="5" value="'.$row['ledBrightness'].'" class="range" oninput="this.nextElementSibling.value=this.value"> <output>'.$row['ledBrightness'].'</output></p>
      <p class="mx-auto"><input id="settingsFormSubmit" class="mt-8 input-text mx-auto" name="settingsFormSubmit" type="submit" value="speichern"></p>
    </form>
  </div>
  '.getHr().'
  <div id="anchorUserAccount" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Benutzereinstellungen</h3>
    <form id="pwChangeForm" action="login.php?do=3" method="post">
      <p class="mx-auto"><input id="pwChangeFormSubmit" class="mt-8 input-text mx-auto" name="pwChangeFormSubmit" type="submit" value="Passwort ändern"></p>
    </form>
  </div>
  '.getHr().'
  <div id="anchorDataExport" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Daten exportieren</h3>
    <p>Deine Messdaten werden im CSV-Format unter `verbrauch.csv` gespeichert</p>
    <br>
    <form id="dataExportForm" action="settings.php?do=1" method="post">
      <p class="mx-auto"><input id="dataExportSubmit" class="mt-8 input-text mx-auto" name="dataExportSubmit" type="submit" value="Messdaten als csv speichern"></p>
    </form>
  </div>  
  ';
} elseif ($doSafe === 1) { // export all entries
  printBeginOfPage_v2(site:'settings.php', title:'Datenexport');
  $result = $dbConn->query('SELECT * FROM `verbrauchArchive` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 24000;'); // limit 24k = bit more than one month. To limit file size
  $num = $result->num_rows;

  echo '
  <div id="anchorDataExport" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Exportierte Daten löschen?</h3>
    <p>Möchtest du die exportierten '.$num.' Einträge aus der Exportdatenbank löschen?</p>
    <p>Der Datenexport ist auf die letzten 24\'000 Einträge (ca. ein Monat) limitiert um die Dateigrösse nicht explodieren zu lassen.</p>
    <br>
    <p>NB: Dies hat keinen Einfluss auf die "Produktiv-Daten", deine Diagramme und Statistiken sind davon nicht betroffen und funktionieren wie gehabt.</p>
    <br>
    <form id="dataExportFormA" action="settings.php?do=4&num='.$num.'" method="post">
      <p class="mx-auto"><input id="dataExportFormASubmit" class="mt-8 input-text mx-auto" name="dataExportFormA" type="submit" value="Exportierte Daten löschen"></p>
    </form>
    <br>
    <form id="dataExportFormB" action="settings.php" method="post">
      <p class="mx-auto"><input id="dataExportFormBSubmit" class="mt-8 input-text mx-auto" name="dataExportFormB" type="submit" value="zurück (keine Daten löschen)"></p>
    </form>
  </div>
  ';

  // this one opens the download process (kind of parallel to the text printed above and then later dies)
  // need to do it this way as I can't continue after the download header thing...
  echo '<script>setTimeout(() => { window.location.href = \'settings.php?do=3\'; }, 2000);</script>';
} elseif ($doSafe === 2) {
  printBeginOfPage_v2(site:'settings.php', title:'Einstellungen');
  $ledMaxValue  = abs(safeIntFromExt(source:'POST',varName:'ledMaxValue', length:4)); // this one is displayed as negative value, stored as positive one though
  $ledMaxValGen = safeIntFromExt(source:'POST',varName:'ledMaxValGen',length:4);
  $ledBrightness = safeIntFromExt(source:'POST',varName:'ledBrightness',length:3);
  $ledMaxValue   = limitInt(input:$ledMaxValue,  lower:0, upper:$LIMIT_LED_MAX_VALUE_CONS);
  $ledMaxValGen  = limitInt(input:$ledMaxValGen, lower:0, upper:$LIMIT_LED_MAX_VAL_GEN);
  $ledBrightness = limitInt(input:$ledBrightness,lower:0, upper:$LIMIT_LED_BRIGHTNESS);

  $result = $dbConn->query('UPDATE `kunden` SET `ledMaxValue` = "'.$ledMaxValue.'", `ledMaxValGen` = "'.$ledMaxValGen.'", `ledBrightness` = "'.$ledBrightness.'" WHERE `id` = "'.$userid.'";');

  echo 'gespeichert<br>';
  echo '<script>setTimeout(() => { window.location.href = \'settings.php\'; }, 2000);</script>';
} elseif ($doSafe === 3) {  // do the export and die afterwards
  header("Content-Type: application/octet-stream");
  header("Content-Transfer-Encoding: Binary");
  header("Content-disposition: attachment; filename=\"verbrauch.csv\"");
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
  $outFile = fopen("php://output", "w");
  fputcsv($outFile, array('id','userid','consumption','consDiff','consNt','consNtDiff','consHt','consHtDiff','gen','genDiff','genNt','genNtDiff','genHt','genHtDiff','zeit','zeitDiff'));
  $result = $dbConn->query('SELECT * FROM `verbrauchArchive` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 24000;'); // limit 24k = bit more than one month. To limit file size
  $exportedLines = $result->num_rows;
  while ($row = $result->fetch_row()) { 
    fputcsv($outFile, $row); 
  }
  fclose($outFile);
  exit();
} elseif ($doSafe === 4) {  // do delete the previously exported data
  printBeginOfPage_v2(site:'settings.php', title:'Datenexport');
  $num = safeIntFromExt(source:'GET', varName:'num', length:5);
  $num = min($num, 24000); // do never delete more than 24k (get param may be changed by user)

  $result = $dbConn->query('DELETE FROM `verbrauchArchive` WHERE `userid` = "'.$userid.'" ORDER BY `id` LIMIT '.$num.';');

  echo '
  <div id="anchorDataExport" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Einträge gelöscht</h3>
    <p>'.$num.' Einträge aus der Exportdatenbank wurden gelöscht</p>
    <br>
    <form id="dataExportFormC" action="settings.php" method="post">
      <p class="mx-auto"><input id="dataExportFormCSubmit" class="mt-8 input-text mx-auto" name="dataExportFormC" type="submit" value="zurück"></p>
    </form>
  </div>
  ';
} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br></div></body></html>
