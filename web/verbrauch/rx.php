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

    function doDbThinning($dbConn, int $userid, int $timeRangeMins):void {
        // 24h-old: thin with a rate of 1 entry per 15 minutes (about a 2/15 rate)
        // 72h-old: thin with a rate of 1 entry per 4 hours (about a 2/240 rate), resulting in 6 entries per day  
        // $timeRangeMins is either 15 or 240
        
        $sqlWhereUseridThin = '`userid` = "'.$userid.'" AND `thin` < "15"';
        if ($timeRangeMins === 240) {
          $sqlWhereUseridThin = '`userid` = "'.$userid.'" AND `thin` < "240"';
        }
        $sql = 'SELECT `zeit` FROM `verbrauch` WHERE '.$sqlWhereUseridThin.' ORDER BY `id` ASC LIMIT 1;';
        $result = $dbConn->query($sql);
        $row = $result->fetch_assoc();
        // get the time, add 15 or 240 minutes  
        $zeitToThin = date_create($row['zeit']);
        $zeitToThin->modify('+ '.$timeRangeMins.' minutes');
        $zeitToThinString = $zeitToThin->format('Y-m-d H:i:s');
        
        $zeitThinStart = date_create("now");
        if ($timeRangeMins === 15) {
          $zeitThinStart->modify('- 24 hours');
        } else {
          $zeitThinStart->modify('- 72 hours');
        }
        
        if ($zeitToThin >= $zeitThinStart) {  // if this time is more then 24h/72h old, proceed. Otherwise stop          
          return;
        }
        // get the last one where thinning was not yet applied
        $sql = 'SELECT `id` FROM `verbrauch` WHERE '.$sqlWhereUseridThin.' AND `zeit` < "'.$zeitToThinString.'" ORDER BY `id` ASC LIMIT 240;';
        $result = $dbConn->query($sql);
        if ($result->num_rows < 7) { // otherwise I can't really compact stuff
          // I have an issue when there are gaps in the entries. I then have less than 7 entries per 15 minutes
          $zeitThinStartWithMargin = $zeitThinStart;
          $zeitThinStartWithMargin->modify('- 2 hours');
          if ($zeitToThin >= $zeitThinStartWithMargin) { // otherwise proceed normally
            return;
          }
        }
        $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
        $idToUpdate = $row['id'];
        
        $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
        $sql = $sql. ' WHERE '.$sqlWhereUseridThin.' AND `zeit` < "'.$zeitToThinString.'";';
        $result = $dbConn->query($sql);
        $row = $result->fetch_assoc(); 
      
        // now do the update and then delete the others. Number 15 means: a ratio of about one data item per 15min was implemented 
        $sql = 'UPDATE `verbrauch` SET `consDiff` = "'.$row['sumConsDiff'].'", `zeitDiff` = "'.$row['sumZeitDiff'].'", `thin` = "'.$timeRangeMins.'" WHERE `id` = "'.$idToUpdate.'";';
        $result = $dbConn->query($sql);
        
        $sql = 'DELETE FROM `verbrauch` WHERE '.$sqlWhereUseridThin.' AND `zeit` < "'.$zeitToThinString.'";';
        $result = $dbConn->query($sql);
        echo $dbConn->affected_rows.' entries have been deleted';
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
        
        // dbThinnings: do not need to run every time but it doesn't hurt either
        doDbThinning(dbConn:$dbConn, userid:$userid, timeRangeMins:15);
        doDbThinning(dbConn:$dbConn, userid:$userid, timeRangeMins:240);
         
    } else {
        echo 'no previous data'; // not an error
    }
    
  
    
?>