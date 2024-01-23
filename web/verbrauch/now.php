<?php declare(strict_types=1); 
require_once('functions.php');
$dbConn = initialize();

$userid = getUserid(); // this will get a valid return because if not, the initialize above will already fail (=redirect)

printBeginOfPage_v2(site:'now.php');

$sql = 'SELECT `consumption`, `gen`, `zeit`, `consDiff`, `zeitDiff`, `genDiff`, `consNt`, `consHt` ';
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
if (date('Y-m-d') === $zeitNewest->format('Y-m-d')) { // same day
  $zeitString = $zeitNewest->format('H:i');
}
echo '<div class="flex">
  <div class="flex-auto text-left"><b><span class="text-green-600">'.$newestGen.'W</span> / <span class="text-red-500">'.$newestCons.'W</span></b></div>
  <div class="flex-auto text-center">'.$zeitString.'</div>
</div>
';


?>
<br><br>
</div></body></html>
