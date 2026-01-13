<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$LIMIT_LED_MIN_VALUE_CON = 2000;
$LIMIT_LED_MAX_VAL_GEN = 8000;
$LIMIT_LED_BRIGHTNESS = 255;

$doSafe = safeIntFromExt(source:'GET', varName:'do', length:2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: export all user data
// do = 2: process setting changes
// do = 3: present the 'delete exported data?'
// do = 4: process 'delete archived'
if ($doSafe === 0) { // entry point of this site
  $result = $dbConn->query(query: "SELECT `ledMinValCon`, `ledMaxValGen`, `ledBrightness`, `rateConW`, `rateConS`, `rateGenW`, `rateGenS` FROM `kunden` WHERE `id` = \"$userid\" LIMIT 1;");
  $row = $result->fetch_assoc();
  
  printBeginOfPage_v2(site:'settings.php');  
  // TODO: image of display
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
        <td width="49%" align="right">Verbrauch&nbsp;&nbsp;</td>
        <td width="2%" align="center">|</td>
        <td width="49%">&nbsp;&nbsp;Einspeisung</td>
      </tr>
      <tr>
        <td colspan="3" align="center"><img class="h-1" width="60%" src="img/redToBlue.png" alt="Farbskala Rot-nach-Blau"></td>
      </tr>
      <tr>
        <td width="49%" align="right">
          <input dir="rtl" id="ledMinValCon" name="ledMinValCon" type="range" min="50" max="'.$LIMIT_LED_MIN_VALUE_CON.'" step="50" value="'.$row['ledMinValCon'].'" class="range w-36" oninput="getElementById(\'tooltip-ledMinValCon-value\').value=this.value">           
          <div id="tooltip-ledMinValCon" class="absolute z-20 inline-block px-3 py-2 my-8 -mx-24 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-sm">
            -<output id="tooltip-ledMinValCon-value">'.$row['ledMinValCon'].'</output>W
          </div>
        </td>
        <td width="2%"></td>
        <td width="49%">
          <input id="ledMaxValGen" name="ledMaxValGen" type="range" min="50" max="'.$LIMIT_LED_MAX_VAL_GEN.'" step="50" value="'.$row['ledMaxValGen'].'" class="range w-36" oninput="getElementById(\'tooltip-ledMaxValGen-value\').value=this.value">           
          <div id="tooltip-ledMaxValGen" class="absolute z-10 inline-block px-3 py-2 my-8 -mx-24 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-sm">
            <output id="tooltip-ledMaxValGen-value">'.$row['ledMaxValGen'].'</output>W
          </div>
        </td>
      </tr>
      </table>
      <br>
      <br>
      <hr>
      <p class="text-left"><b>LED Helligkeit:</b><br>
      Die Helligkeit der farbigen LED. Von 0 (ausgeschaltet) bis 255.<br>
      In der Nacht (21 Uhr bis 6 Uhr) leuchtet sie übrigens 75% dunkler.</p>
      <p class="mx-auto">
        <input id="ledBrightness" name="ledBrightness" type="range" min="0" max="'.$LIMIT_LED_BRIGHTNESS.'" step="5" value="'.$row['ledBrightness'].'" class="range w-36" oninput="getElementById(\'tooltip-ledBrightness-value\').value=this.value">
        <div id="tooltip-ledBrightness" class="absolute z-20 inline-block px-3 py-2 -my-8 mx-48 text-sm font-medium text-white bg-gray-900 rounded-lg shadow-sm">
          <output id="tooltip-ledBrightness-value">'.$row['ledBrightness'].'</output>
        </div>    
      </p>
      <br>
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
  <div id="anchorCost" class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Strompreise</h3>
    <form id="CostForm" action="settings.php?do=5" method="post">
      <div class="flex flex-row mt-8">
        <div class="basis-2/3 self-center">Verbrauch Sommer, in CHF pro kWh</div>
        <div class="basis-1/3 inline-block align-middle"><input class="input-text w-20" name="rateConS" type="text" maxlength="6" value="'.$row['rateConS'].'" required></div>
      </div>
      <div class="flex flex-row mt-2">
        <div class="basis-2/3 self-center">Einspeisung Sommer, in CHF pro kWh</div>
        <div class="basis-1/3 inline-block align-middle"><input class="input-text w-20" name="rateGenS" type="text" maxlength="6" value="'.$row['rateGenS'].'" required></div>
      </div>
      <div class="flex flex-row mt-8">
        <div class="basis-2/3 self-center">Verbrauch Winter, in CHF pro kWh</div>
        <div class="basis-1/3 inline-block align-middle"><input class="input-text w-20" name="rateConW" type="text" maxlength="6" value="'.$row['rateConW'].'" required></div>
      </div>
      <div class="flex flex-row mt-2">
        <div class="basis-2/3 self-center">Einspeisung Winter, in CHF pro kWh</div>
        <div class="basis-1/3 inline-block align-middle"><input class="input-text w-20" name="rateGenW" type="text" maxlength="6" value="'.$row['rateGenW'].'" required></div>
      </div>
      <div class="flex flex-row justify-center mt-2">
        <div><br><input id="CostFormSubmit" class="mt-8 input-text mx-auto" name="CostFormSubmit" type="submit" value="Strompreise speichern"></div>
      </div>
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
  $result = $dbConn->query(query: "SELECT * FROM `verbrauch_26Archive` WHERE `userid` = \"$userid\" ORDER BY `id` DESC LIMIT 24000;"); // limit 24k = bit more than one month. To limit file size
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
  $ledMinValCon  = abs(num:safeIntFromExt(source:'POST',varName:'ledMinValCon', length:4)); // this one is displayed as negative value, stored as positive one though
  $ledMaxValGen = safeIntFromExt(source:'POST',varName:'ledMaxValGen',length:4);
  $ledBrightness = safeIntFromExt(source:'POST',varName:'ledBrightness',length:3);
  $ledMinValCon  = limitInt(input:$ledMinValCon, lower:0, upper:$LIMIT_LED_MIN_VALUE_CON);
  $ledMaxValGen  = limitInt(input:$ledMaxValGen, lower:0, upper:$LIMIT_LED_MAX_VAL_GEN);
  $ledBrightness = limitInt(input:$ledBrightness,lower:0, upper:$LIMIT_LED_BRIGHTNESS);

  $result = $dbConn->query(query:"UPDATE `kunden` SET `ledMinValCon` = \"$ledMinValCon\", `ledMaxValGen` = \"$ledMaxValGen\", `ledBrightness` = \"$ledBrightness\" WHERE `id` = \"$userid\";");

  echo 'gespeichert<br>';
  echo '<script>setTimeout(() => { window.location.href = \'settings.php\'; }, 2000);</script>';
} elseif ($doSafe === 3) {  // do the export and die afterwards
  header(header:'Content-Type: application/octet-stream');
  header(header:'Content-Transfer-Encoding: Binary');
  header(header:'Content-disposition: attachment; filename="verbrauch.csv"');
  /*
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `con` decimal(10,3) NOT NULL,
  `conDiff` decimal(10,3) NOT NULL,
  `conRate` decimal(5,4) NOT NULL,
  `gen` decimal(10,3) NOT NULL,
  `genDiff` decimal(10,3) NOT NULL,
  `genRate` decimal(5,4) NOT NULL,
  `zeit` timestamp NOT NULL DEFAULT current_timestamp(),
  `zeitDiff` int(11) NOT NULL,
  `thin` smallint(5) UNSIGNED NOT NULL DEFAULT 0 --> this is not exported, always 0 for the archive
  */
  $outFile = fopen(filename:'php://output', mode: 'w');
  fputcsv(stream: $outFile,      fields: ['id','userid','con','conDiff','conRate','gen','genDiff','genRate','zeit','zeitDiff']);
  $result = $dbConn->query(query: "SELECT `id`,`userid`,`con`,`conDiff`,`conRate`,`gen`,`genDiff`,`genRate`,`zeit`,`zeitDiff` FROM `verbrauch_26Archive` WHERE `userid` = \"$userid\" ORDER BY `id` DESC LIMIT 24000;"); // limit 24k = bit more than one month. To limit file size
  $exportedLines = $result->num_rows;
  while ($row = $result->fetch_row()) { 
    fputcsv(stream:$outFile, fields:$row); 
  }
  fclose(stream:$outFile);
  exit();
} elseif ($doSafe === 4) {  // do delete the previously exported data
  printBeginOfPage_v2(site:'settings.php', title:'Datenexport');
  $num = safeIntFromExt(source:'GET', varName:'num', length:5);
  $num = min($num, 24000); // do never delete more than 24k (get param may be changed by user)

  $result = $dbConn->query(query: "DELETE FROM `verbrauchArchive` WHERE `userid` = \"$userid\" ORDER BY `id` LIMIT $num;");

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
} elseif ($doSafe === 5) {
  printBeginOfPage_v2(site:'settings.php', title:'Einstellungen');
  $rateConS = abs(safeFloatFromExt(source:'POST',varName:'rateConS', length:6));
  $rateGenS = abs(safeFloatFromExt(source:'POST',varName:'rateGenS', length:6));
  $rateConW = abs(safeFloatFromExt(source:'POST',varName:'rateConW', length:6));
  $rateGenW = abs(safeFloatFromExt(source:'POST',varName:'rateGenW', length:6));

  $result = $dbConn->query(query: "UPDATE `kunden` SET `rateConS` = \"$rateConS\", `rateGenS` = \"$rateGenS\", `rateConW` = \"$rateConW\", `rateGenW` = \"$rateGenW\" WHERE `id` = \"$userid\";");

  echo 'gespeichert<br>';
  echo '<script>setTimeout(() => { window.location.href = \'settings.php\'; }, 2000);</script>';

} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br></div></body></html>
