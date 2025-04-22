<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

printBeginOfPage_v2(site:'now.php');

$sql = 'SELECT `cons`, `gen`, `zeit`, `consDiff`, `zeitDiff`, `genDiff`, `consNt`, `consHt` ';
$sql .= 'from `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `zeit` DESC LIMIT 1;';

$result = $dbConn->query($sql);
$rowNewest = $result->fetch_assoc();

if ($rowNewest['zeitDiff'] > 0) { // divide by 0 exception
    $newestCons = round($rowNewest['consDiff']*3600*1000 / $rowNewest['zeitDiff']); // kWh compared to seconds
    $newestGen = round($rowNewest['genDiff']*3600*1000 / $rowNewest['zeitDiff']);
} else { 
  $newestCons = 0.0;
  $newestGen = 0.0;
}

$zeitNewest = date_create($rowNewest['zeit']);
$zeitString = $zeitNewest->format('Y-m-d H:i');

echo '
<div class="text-left mt-8">
<table>
  <tr><td>Messzeit: </td><td>'.$zeitString.'</td></tr>
  <tr><td><b>Aktuelle Einspeisung:&nbsp;</b></td><td><b><span class="text-green-600">'.$newestGen.' W</span></b></td></tr>
  <tr><td><b>Aktueller Verbrauch: </b></td><td><b><span class="text-red-500">'.$newestCons.' W</span></b></td></tr>
  <tr><td>Einspeisung: </td><td>'.$rowNewest['gen'].' kWh</td></tr>
  <tr><td>Verbrauch: </td><td>'.$rowNewest['cons'].' kWh</td></tr>
  <tr><td>Verbrauch NT: </td><td>'.$rowNewest['consNt'].' kWh</td></tr>
  <tr><td>Verbrauch HT: </td><td>'.$rowNewest['consHt'].' kWh</td></tr>
</table>
<p>&nbsp;</p>
<p>Diese Seite aktualisiert sich alle 90 Sekunden.</p>
</div>
';


?>
<br><br>
</div></body></html>
