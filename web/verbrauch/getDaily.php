<?php declare(strict_types=1); 
require_once 'functions.php';
$dbConn = initialize();

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

printBeginOfPage_v2(site:'now.php'); // FIXME

$resultKunden = $dbConn->query(query: "SELECT `priceConsHt`,`priceConsNt`, `priceGen` FROM `kunden` WHERE `id` = \"$userid\" LIMIT 1;");
if ($resultKunden->num_rows !== 1) {
    printRawErrorAndDie(heading: 'Error', text: 'no config data');
} 
$rowKunden = $resultKunden->fetch_assoc();

$zeitNewest = date_create(datetime: 'now');
$zeitOldestString = $zeitNewest->format(format: 'Y-m-d 00:00:00'); // beginning of the current day
 
$sql = "SELECT `gen`, `consNt`, `consHt` from `verbrauch` WHERE `userid` = \"$userid\" AND `zeit` > \"$zeitOldestString\" ";
$sql .= 'ORDER BY `zeit` DESC;';

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
  $text = 'Ertrag';
} else {
  $color = 'text-red-500';
  $text = 'Kosten';
}

echo '
<div class="text-left mt-8">
<table>
  <tr><td>Messzeit:&nbsp;</td><td>'.$zeitOldestString.' bis '.$zeitNewest->format(format: 'Y-m-d H:i:s').'</td></tr>
  <tr><td><b>'.$text.':&nbsp;</b></td><td><b><span class="'.$color.'">'.$cost.' CHF</span></b></td></tr>
</table>
<p>&nbsp;</p>
<p>Diese Seite aktualisiert sich alle 90 Sekunden.</p>
</div>
';


?>
<br><br>
</div></body></html>
