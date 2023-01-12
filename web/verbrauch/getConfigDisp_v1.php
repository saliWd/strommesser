<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "<file>.php?TX=pico&TXVER=2" including POST data (url encoded)
    
    // no meaningful (=HTML) output is generated
    if (! verifyGetParams()) { // now I can look at the post variables        
        printRawErrorAndDie('Error', 'invalid params');
    }
    $deviceValid = validDevice($dbConn, 'device');
    if (! $deviceValid[0]) {
        printRawErrorAndDie('Error', 'device not supported');
    }
    $device = $deviceValid[1];

    if (! checkHash($dbConn, $device)) {
        printRawErrorAndDie('Error', 'access key not ok');
    }    
    $result = $dbConn->query('SELECT `ledMinValue`,`ledMaxValue`,`ledBrightness` FROM `user` WHERE `device` = "'.$device.'" ORDER BY `id` LIMIT 1');
    if ($result->num_rows !== 1) {
        printRawErrorAndDie('Error', 'no data');
    } 
    $row = $result->fetch_assoc();
    // not doing consistency checks (min < max and stuff) because this is done at edit/insert of the values
    
    // min|max|brightness
    echo $row['ledMinValue'].'|'.$row['ledMaxValue'].'|'.$row['ledBrightness'];
?>