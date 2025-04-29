<?php declare(strict_types=1); 
    require_once 'functions.php';
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "<file>.php?TX=pico&TXVER=3" including POST data (url encoded)
    
    $userid = checkInputs(dbConn: $dbConn);

    $result = $dbConn->query(query: 'SELECT `zeit` FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 1;');
    $queryCount = $result->num_rows; // this may be 0 or 1
    if ($queryCount !== 1) {
        printRawErrorAndDie(heading: 'Error', text: 'no meas data');
    } 
    $row = $result->fetch_assoc();

    $zeitNewest = date_create(datetime: $row['zeit']);
    $zeitNow = date_create(datetime: 'now');
    $zeitNow->modify(modifier: '- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    if ($zeitNewest > $zeitNow) {
        $valid = 1;
    } else {
        $valid = 0;
    }

    $result = $dbConn->query(query: 'SELECT `ledMaxValue`,`ledBrightness`, `ledMaxValGen` FROM `kunden` WHERE `id` = "'.$userid.'" LIMIT 1;');
    if ($result->num_rows !== 1) {
        printRawErrorAndDie(heading: 'Error', text: 'no config data');
    } 
    $rowKunden = $result->fetch_assoc();
    
    echo $valid.'|'.$rowKunden['ledBrightness'].'|'.$rowKunden['ledMaxValue'].'|'.$rowKunden['ledMaxValGen'];
?>