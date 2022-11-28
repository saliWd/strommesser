<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

function printBeginOfPage_settings():void {
  echo '<!DOCTYPE html><html><head>
  <meta charset="utf-8" />
  <title>StromMesser Einstellungen</title>
  <meta name="description" content="zeigt deinen Energieverbrauch" />  
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="css/verbrauch.css" type="text/css" />
  </head><body>';
  printNavMenu(getCurrentSite());
  echo '
  <div class="section noBottom">
  <div class="container">
  <h1>Einstellungen</h1>
  <p>&nbsp;</p>
  <hr>';
  return;
}

$doSafe = safeIntFromExt('GET', 'do', 2); // this is an integer (range 1 to 99) or non-existing
// do = 0: entry point
// do = 1: delete all entries in DB
// do = 2: data thinning for older entries with  15mins timerange (TODO: remove this again from this side, it's integrated into rx page now)
// do = 3: data thinning for older entries with 240mins timerange (TODO: remove this again from this side, it's integrated into rx page now)
$device = 'austr10'; // TODO: device as variable

if ($doSafe === 0) { // entry point of this site
    printBeginOfPage_settings();
    echo '          
        <div class="row twelve columns"><div class="button"><a href="settings.php?do=2">alte Einträge reduzieren: 1/15 Faktor (manuell, einmalig)</a></div></div>
        <div class="row twelve columns"><div class="button"><a href="settings.php?do=3">alte Einträge reduzieren: 1/240 Faktor (manuell, einmalig)</a></div></div>
        <div class="row twelve columns">&nbsp;</div>
        <div class="row twelve columns"><div class="button"><a href="settings.php?do=1">alle Einträge löschen</a></div></div>';
} elseif ($doSafe === 1) { // delete all entries
  printBeginOfPage_settings();
  echo '<div class="row twelve columns">Sorry, diese Funktion ist aktuell deaktiviert... <a href="settings.php">zurück</a>...</div>';
  /*
  $result = $dbConn->query('DELETE FROM `verbrauch` WHERE `device` = "'.$device.'"');
  if ($result) {
    echo '<div class="row twelve columns">...alle Einträge gelöscht. <a href="settings.php">zurück</a>...</div>';
  } else {
    echo '<div class="row twelve columns">...something went wrong when deleting all entries...</div>';
  }
  */
} elseif ($doSafe === 2) { // data thinning for older entries
  printBeginOfPage_settings();
  doDbThinning($dbConn, $device, TRUE, 15);
} elseif ($doSafe === 3) { // data thinning for older entries
  printBeginOfPage_settings();
  doDbThinning($dbConn, $device, TRUE, 240);
} else { // should never happen
  echo '<div class="row twelve columns">...something went wrong (undefined do-variable)...</div>';
}
?>
<div class="row twelve columns">&nbsp;</div>
</div></div></body></html>
