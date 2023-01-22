<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "https://strommesser.ch/verbrauch/rx_v2.php?TX=pico&TXVER=2"
    // with POST data (url encoded)

    // I want to readout: total_consumption NB: phases do not help that much without cosphi                
    function getInterestingValues (string $haystack, string $param_consumption): array {        
        // process the whole IR string
        // \x02F.F(00)                     
        // 0.0(          120858)
        // C.1.0(13647123)
        // C.1.1(        )
        // 1.8.1(042951.721*kWh)       total_nt_consumption
        // 1.8.2(018609.568*kWh)       total_ht_consumption
        // 2.8.1(000000.302*kWh)       total_nt_generation
        // 2.8.2(000010.188*kWh)       total_ht_generation
        // 1.8.0(061561.289*kWh)       total_consumption
        // 2.8.0(000010.490*kWh)       total_generation
        // 15.8.0(061571.780*kWh)      total_energy
        // C.7.0(0008)                 power_off_counter
        // 32.7(241*V)                 phase_0_volt
        // 52.7(243*V)                 phase_1_volt
        // 72.7(242*V)                 phase_2_volt
        // 31.7(000.35*A)              phase_0_amp
        // 51.7(000.52*A)              phase_1_amp
        // 71.7(000.47*A)              phase_2_amp
        // 82.8.1(0000)
        // 82.8.2(0000)
        // 0.2.0(M26)
        // C.5.0(0401)
        // !
        // x03\x01'
        $return_array = array(FALSE, ''); // valid/consumption
        $consumption_pos = strpos($haystack,$param_consumption);
        if ($consumption_pos) {
            $return_array[1] = substr($haystack,$consumption_pos+6,10); // I know it's 10 characters long and starts after the bracket
            $return_array[0] = TRUE; // only true if value haS been found                
        }
        return $return_array;
    }
    
    $userid = checkInputs($dbConn);

    $sqlSafe_ir_answer = sqlSafeStrFromPost($dbConn, 'ir_answer', 511); // safe to insert into sql (not to output on html)   
    // interested in total_consumption param (unfortunately no 16.7 and no cosPhi param. So phase-values are just indicative)
    $values = getInterestingValues($sqlSafe_ir_answer, "1.8.0(");
    if (! $values[0]) {
        printRawErrorAndDie('Error', 'values not found');
    }
    $total_consumption = $values[1];

    if (! $result = $dbConn->query('INSERT INTO `verbrauch` (`userid`, `consumption`) VALUES ("'.$userid.'", "'.$total_consumption.'")')) {
        printRawErrorAndDie('Error', 'db insert not ok');
    }


    //NB: not using last inserted ID as other inserts may have happened in the meantime
    $result = $dbConn->query('SELECT * FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 2');
    $queryCount = $result->num_rows; // this may be 1 or 2
    if ($queryCount === 2) {
        $row_now = $result->fetch_assoc();
        $row_before = $result->fetch_assoc();
        $consDiff = $row_now['consumption'] - $row_before['consumption']; // 0 or positive                        
        $zeitDiff = date_diff(date_create($row_before['zeit']), date_create($row_now['zeit']));
        $zeitSecs = ($zeitDiff->d * 24 * 3600) + ($zeitDiff->h*3600) + ($zeitDiff->i * 60) + ($zeitDiff->s);
        
        $result = $dbConn->query('UPDATE `verbrauch` SET `consDiff` = "'.$consDiff.'", `zeitDiff` = "'.$zeitSecs.'" WHERE `id` = "'.$row_now['id'].'";');
        
        $result = $dbConn->query('UPDATE `verbrauch` SET `aveConsDiff` = "'.$consDiff.'", `aveZeitDiff` = "'.$zeitSecs.'" WHERE `id` = "'.$row_now['id'].'";');
        // dbThinnings: do not need to run every time but it doesn't hurt either
        doDbThinningUserid($dbConn, $userid, FALSE, 15);
        doDbThinningUserid($dbConn, $userid, FALSE, 240);
         
    } else {
        echo 'no previous data'; // not an error
    }
    
  
    
?>