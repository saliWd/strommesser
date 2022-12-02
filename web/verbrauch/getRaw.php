<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "https://strommesser.ch/verbrauch/getRaw.php?TX=pico&TXVER=2" including POST data (url encoded)
    
    // no meaningful (=HTML) output is generated. Use index.php to monitor the value itself
    if (! verifyGetParams()) { // now I can look the post variables        
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

    $result = $dbConn->query('SELECT * FROM `verbrauch` WHERE `device` = "'.$device.'" ORDER BY `id` DESC LIMIT 1');
    $queryCount = $result->num_rows; // this may be 0 or 1
    if ($queryCount !== 1) {
        printRawErrorAndDie('Error', 'no data');
    } 
    $row = $result->fetch_assoc();
    if ($row['aveZeitDiff'] > 0) { // divide by 0 exception
        $newestConsumption = round($row['aveConsDiff']*3600*1000 / $row['aveZeitDiff']); // kWh compared to seconds
    } else { $newestConsumption = 0.0; }
    $zeitNewest = date_create($row['zeit']);
    $zeitNow = date_create("now");
    $zeitNow->modify('- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    if ($zeitNewest > $zeitNow) {
        $valid = 1;
    } else {
        $valid = 0;
    }
    echo $valid.'|'.$newestConsumption;    
?>