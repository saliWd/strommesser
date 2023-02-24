<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$LIMIT_LED_MAX_VALUE = 2000;
$LIMIT_LED_BRIGHTNESS = 255;

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: (previously: delete all entries in DB)
// do = 2: process setting changes
printBeginOfPage(site:'settings.php', title:'Einstellungen');
if ($doSafe === 0) { // entry point of this site
    $result = $dbConn->query('SELECT `ledMaxValue`,`ledBrightness` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
    $row = $result->fetch_assoc();

    echo '
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
    <hr class="my-8">
    <div class="flex flex-row justify-center">
      <div><a href="login.php?do=3" class="input-text basis-full">Passwort ändern</a></div>
    </div>
    ';

    // echo '<p>&nbsp;</p><p>&nbsp;</p><p><div class="btn mt-8 input-text"><a href="settings.php?do=1">alle Einträge löschen</a></div></p>';        
} elseif ($doSafe === 1) { // delete all entries
  echo '<p>Sorry, diese Funktion ist aktuell deaktiviert... <a href="settings.php">zurück</a>...</p>';
  /*
  $result = $dbConn->query('DELETE FROM `verbrauch` WHERE `userid` = "'.$userid.'"');
  if ($result) {
    echo '<div class="row twelve columns">...alle Einträge gelöscht. <a href="settings.php">zurück</a>...</div>';
  } else {
    echo '<div class="row twelve columns">...something went wrong when deleting all entries...</div>';
  }
  */
} elseif ($doSafe === 2) {
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
