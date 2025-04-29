<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "<file>.php?TX=pico&TXVER=2" including POST data (url encoded)
    
    $userid = checkInputs($dbConn);

    $result = $dbConn->query('SELECT `consDiff`, `zeitDiff`, `genDiff`, `zeit` FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 1');
    $queryCount = $result->num_rows; // this may be 0 or 1
    if ($queryCount !== 1) {
        printRawErrorAndDie('Error', 'no meas data');
    } 
    $row = $result->fetch_assoc();

    if ($row['zeitDiff'] > 0) { // divide by 0 exception
        $newestCons = round($row['consDiff']*3600*1000 / $row['zeitDiff']); // kWh compared to seconds
        $newestGen  = round($row['genDiff']*3600*1000 / $row['zeitDiff']);
    } else { 
        $newestCons = 0;
        $newestGen  = 0;
    }
    $zeitNewest = date_create($row['zeit']);
    $zeitNow = date_create("now");
    $zeitNow->modify('- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    if ($zeitNewest > $zeitNow) {
        $valid = 1;
    } else {
        $valid = 0;
    }

    $result = $dbConn->query('SELECT `ledMaxValue`,`ledBrightness`, `ledMaxValGen` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
    if ($result->num_rows !== 1) {
        printRawErrorAndDie('Error', 'no config data');
    } 
    $rowKunden = $result->fetch_assoc();
    
    echo $valid.'|'.$newestCons.date_format($zeitNewest,"|Y|m|d|H|i|s|").$rowKunden['ledMaxValue'].'|'.$rowKunden['ledBrightness'].'|'.$newestGen.'|'.$rowKunden['ledMaxValGen'];
?>