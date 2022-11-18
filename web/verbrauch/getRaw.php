<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = initialize();
    // expecting a call like "https://widmedia.ch/wmeter/getRaw.php?TX=pico&TXVER=2"
    // with POST data (url encoded)

    function printRawErrorAndDie (string $heading, string $text): void {
        echo $heading.': '.$text;
        die();
    }  

    // checks the params retrieved over get and returns TRUE if they are ok
    function verifyGetParams (): bool {  
        if (safeStrFromExt('GET','TX', 4) !== 'pico') {                
            return FALSE;
        }
        if (safeIntFromExt('GET','TXVER', 1) !== 2) { // don't accept other interface version numbers
            return FALSE;
        }
        return TRUE;
    }
    
    // sql sanitation and length limitation
    function sqlSafeStrFromPost ($dbConn, string $varName, int $length): string {
        if (isset($_POST[$varName])) {
           return mysqli_real_escape_string($dbConn, (substr($_POST[$varName], 0, $length))); // length-limited variable           
        } else {
           return '';
        }
    }

    function validDevice ($dbConn, string $postIndicator): array {        
        $unsafeDevice = safeStrFromExt('POST', $postIndicator, 8); // maximum length of 8
        $result = $dbConn->query('SELECT `device` FROM `wmeter_user` WHERE 1 ORDER BY `id`;');
        while ($row = $result->fetch_assoc()) {
            if ($unsafeDevice === $row['device']) {
                return array(TRUE, $row['device']);
            }
        }
        return array(FALSE, ''); // valid/deviceString
    }

    function checkHash ($dbConn, string $device): bool {
        $unsafeRandNum = safeIntFromExt('POST', 'randNum', 8); // range 1 to 10'000 (0 excluded)
        $unsafePostHash = safeHexFromExt('POST', 'hash', 64); 
        if ($unsafeRandNum === 0 or $unsafePostHash === '') {
            return FALSE;
        }
        $result = $dbConn->query('SELECT * FROM `wmeter_user` WHERE `device` = "'.$device.'" ORDER BY `id` DESC LIMIT 1');
        if ($result->num_rows !== 1) {
            return FALSE;
        }
        $row = $result->fetch_assoc();
        // now do a hash over randNum and the post_key. if that one matches the transmitted hash, we are ok.
        $unsafeRandNum = (string)$unsafeRandNum; // convert the int to a string
        $rxSideHash = hash('sha256',$unsafeRandNum.$row['post_key']);
        if ($rxSideHash === $unsafePostHash) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
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

    $result = $dbConn->query('SELECT * FROM `wmeter` WHERE `device` = "'.$device.'" ORDER BY `id` DESC LIMIT 1');
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