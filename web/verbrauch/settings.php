<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

$LIMIT_LED_MAX_VALUE = 2000;
$LIMIT_LED_BRIGHTNESS = 255;

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: delete all entries in DB
// do = 2: process setting changes
printBeginOfPage(enableReload:FALSE, timerange:'', site:'settings.php');
if ($doSafe === 0) { // entry point of this site
    $result = $dbConn->query('SELECT `ledMaxValue`,`ledBrightness` FROM `user` WHERE `id` = "'.$userid.'" LIMIT 1;');
    $row = $result->fetch_assoc();

    echo '
    <p>&nbsp;</p>
    <form id="settingsValues" action="settings.php?do=2" method="post">
      <table width="100%" style="line-height:4.6;">
        <tr>
          <td width="50%" align="left">Maximalwert Farbskala:</td>
          <td width="50%" align="center"><input id="ledMaxValue" name="ledMaxValue" type="range" min="100" max="'.$LIMIT_LED_MAX_VALUE.'" step="10" value="'.$row['ledMaxValue'].'" class="range" oninput="this.nextElementSibling.value=this.value"/> <output>'.$row['ledMaxValue'].'</output>W</td>
        </tr>
        <tr>
          <td width="50%" align="left">LED Helligkeit:</td>
          <td width="50%" align="center"><input id="ledBrightness" name="ledBrightness" type="range" min="0" max="'.$LIMIT_LED_BRIGHTNESS.'" step="2" value="'.$row['ledBrightness'].'" class="range" oninput="this.nextElementSibling.value=this.value"/> <output>'.$row['ledBrightness'].'</output></td>
        </tr>
        <tr>
          <td colspan="2" align="center"><input id="settingsFormSubmit" class="mt-8 input-text" name="settingsFormSubmit" type="submit" value="speichern"></td>
        </tr>
      </table>          
    </form>';

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

  $result = $dbConn->query('UPDATE `user` SET `ledMaxValue` = "'.$ledMaxValue.'", `ledBrightness` = "'.$ledBrightness.'" WHERE `id` = "'.$userid.'";');

  echo 'gespeichert<br>';
  echo '<script>setTimeout(() => { window.location.href = \'settings.php\'; }, 1500);</script>';
} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
</div></body></html>
