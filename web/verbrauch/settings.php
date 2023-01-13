<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: delete all entries in DB
printBeginOfPage(enableReload:FALSE, timerange:'', site:'settings.php');
if ($doSafe === 0) { // entry point of this site
    echo '          
        <p>&nbsp;</p>
        <p><div class="btn"><a href="settings.php?do=1">alle Einträge löschen</a></div></p>';
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
} else { // should never happen
  echo '<p>...something went wrong (undefined do-variable)...</p>';
}
?>
</div></body></html>
