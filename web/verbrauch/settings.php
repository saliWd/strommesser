<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();
$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)


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
          <td width="50%" align="center"><input id="ledMaxValue" name="ledMaxValue" type="range" min="100" max="2000" value="'.$row['ledMaxValue'].'" class="range" /></td>
        </tr>
        <tr>
          <td width="50%" align="left">LED Helligkeit:</td>
          <td width="50%" align="center"><input id="ledBrightness" name="ledBrightness" type="range" min="0" max="255" value="'.$row['ledBrightness'].'" class="range" /></td>
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
  $ledMaxValue = safeStrFromExt(source:'POST',varName:'ledMaxValue',length:4);
  $ledBrightness = safeStrFromExt(source:'POST',varName:'ledBrightness',length:3);
  echo 'ledMaxValue: '.$ledMaxValue.', ledBrightness:'.$ledBrightness.'<br>';
  echo '<a href="settings.php">zurück</a>';



} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
</div></body></html>
