<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

printBeginOfPage_v2(site:'now.php');

$sql = 'SELECT `cons`, `gen`, `zeit`, `consDiff`, `zeitDiff`, `genDiff`, `consNt`, `consHt` ';
$sql .= "from `verbrauch` WHERE `userid` = \"$userid\" ORDER BY `zeit` DESC LIMIT 1;";

$result = $dbConn->query(query: $sql);
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


// daily cost (TODO: move into function, use in pico2w_v4)
$resultKunden = $dbConn->query(query: "SELECT `priceConsHt`,`priceConsNt`, `priceGen` FROM `kunden` WHERE `id` = \"$userid\" LIMIT 1;");
if ($resultKunden->num_rows !== 1) {
    printRawErrorAndDie(heading: 'Error', text: 'no config data');
} 
$rowKunden = $resultKunden->fetch_assoc();

$zeitOldestString = $zeitNewest->format(format: 'Y-m-d 00:00:00'); // beginning of the current day
 
$sql = "SELECT `gen`, `consNt`, `consHt` from `verbrauch` WHERE `userid` = \"$userid\" AND `zeit` > \"$zeitOldestString\" ORDER BY `zeit` DESC;";

$result = $dbConn->query(query: $sql);
$result->data_seek(offset: $result->num_rows - 1); // skip to the last entry of the rows
$rowOldest = $result->fetch_assoc();
$result->data_seek(offset: 0); // go back to the first row
$row = $result->fetch_assoc();

$cost = -1.0 * 
            (($row['consNt'] - $rowOldest['consNt'])*$rowKunden['priceConsNt'] +
            ($row['consHt'] - $rowOldest['consHt'])*$rowKunden['priceConsHt'] -
            ($row['gen'] - $rowOldest['gen'])*$rowKunden['priceGen']);
$cost = round(num: $cost,precision: 2);
if ($cost > 0) {
  $color = 'text-green-600';
  $text = 'Tagesertrag';
} else {
  $color = 'text-red-500';
  $text = 'Tageskosten';
}


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
  <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>Messzeit Ertrag/Kosten:&nbsp;</td><td>Heute 00:00 Uhr bis '.$zeitNewest->format(format: 'Y-m-d H:i:s').'</td></tr>
  <tr><td><b>'.$text.':&nbsp;</b></td><td><b><span class="'.$color.'">'.number_format(num:(float)$cost,decimals:2,decimal_separator:'.',thousands_separator:'').' CHF</span></b></td></tr>
</table>
<p>&nbsp;</p>
<p>Diese Seite aktualisiert sich alle 90 Sekunden.</p>
</div>
';


?>
<br><br>
</div></body></html>
