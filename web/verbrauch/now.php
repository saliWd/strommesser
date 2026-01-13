<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

printBeginOfPage_v2(site:'now.php');

$sql = 'SELECT `con`, `gen`, `zeit`, `conDiff`, `zeitDiff`, `genDiff`';
$sql .= "from `verbrauch_26` WHERE `userid` = \"$userid\" ORDER BY `zeit` DESC LIMIT 1;";

$result = $dbConn->query(query: $sql);
$rowNewest = $result->fetch_assoc();

if ($rowNewest['zeitDiff'] > 0) { // divide by 0 exception
    $newestCon = round($rowNewest['conDiff']*3600*1000 / $rowNewest['zeitDiff']); // kWh compared to seconds
    $newestGen = round($rowNewest['genDiff']*3600*1000 / $rowNewest['zeitDiff']);
} else { 
  $newestCon = 0.0;
  $newestGen = 0.0;
}

$zeitNewest = date_create(datetime: $rowNewest['zeit']);

$earn = getDailyCost(dbConn:$dbConn, userid:$userid);
if ($earn > 0) {
  $color = 'text-green-600';
  $text = 'Tagesertrag';
} else {
  $color = 'text-red-500';
  $text = 'Tageskosten';
}


echo '
<div class="text-left mt-8">
<table>
  <tr><td>Messzeit: </td><td>'.$zeitNewest->format(format: 'Y-m-d H:i').'</td></tr>
  <tr><td><b>Aktuelle Einspeisung:&nbsp;</b></td><td><b><span class="text-green-600">'.$newestGen.' W</span></b></td></tr>
  <tr><td><b>Aktueller Verbrauch: </b></td><td><b><span class="text-red-500">'.$newestCon.' W</span></b></td></tr>
  <tr><td>Einspeisung: </td><td>'.$rowNewest['gen'].' kWh</td></tr>
  <tr><td>Verbrauch: </td><td>'.$rowNewest['con'].' kWh</td></tr>  
  <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
  <tr><td>Messzeit Ertrag/Kosten:&nbsp;</td><td>Heute 00:00 Uhr bis '.$zeitNewest->format(format: 'Y-m-d H:i:s').'</td></tr>
  <tr><td><b>'.$text.':&nbsp;</b></td><td><b><span class="'.$color.'">'.number_format(num:(float)$earn,decimals:2,decimal_separator:'.',thousands_separator:'').' CHF</span></b></td></tr>
</table>
<p>&nbsp;</p>
<p>Diese Seite aktualisiert sich alle 90 Sekunden.</p>
</div>
';


?>
<br><br>
</div></body></html>
