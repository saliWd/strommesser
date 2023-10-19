<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "https://strommesser.ch/verbrauch/rx.php?TX=pico&TXVER=2"
    // with POST data (url encoded)

    // I want to readout: consumption and generation, both with total/Nt/Ht values. NB: phases do not help that much without cosphi
    function getInterestingValues (string $haystack): array {        
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
      
      $needleStrings = array("1.8.0(", "1.8.1(", "1.8.2(", "2.8.0(", "2.8.1(", "2.8.2("); // consumption,nt,ht, generation,nt,ht
      $return_array = array(TRUE, '', '', '', '', '', ''); // valid and 6 values (as string)
      
      for($i = 0; $i < 6; $i++) {
        $position = strpos($haystack,$needleStrings[$i]);
        if ($position) {
          $return_array[($i+1)] = substr($haystack,$position+6,10); // I know it's 10 characters long and starts after the bracket
        } else {
          $return_array[0] = FALSE; // only true if value has been found
          break; // leave the for loop
        }
      }
      return $return_array;
    }

    function getDiffs($row_now, $row_before):string {
      // `consumption` -> `consDiff`
      // `consNt`      -> `consNtDiff`
      // `consHt`      -> `consHtDiff`
      // `gen`         -> `genDiff`
      // `genNt`       -> `genNtDiff`
      // `genHt`       -> `genHtDiff`
      $sqlString = '';
      $sqlString .= '`consDiff` = "'.($row_now['consumption'] - $row_before['consumption']).'", ';      
      $sqlString .= '`consNtDiff` = "'.($row_now['consNt'] - $row_before['consNt']).'", '; 
      $sqlString .= '`consHtDiff` = "'.($row_now['consHt'] - $row_before['consHt']).'", '; 
      $sqlString .= '`genDiff` = "'.($row_now['gen'] - $row_before['gen']).'", '; 
      $sqlString .= '`genNtDiff` = "'.($row_now['genNt'] - $row_before['genNt']).'", ';
      $sqlString .= '`genHtDiff` = "'.($row_now['genHt'] - $row_before['genHt']).'", ';

      return $sqlString;
    }

    function doReduction($dbConn, int $userid, bool $smlTimeScale):void {
      if ($smlTimeScale) {
        $sqlNoThin = '`userid` = "'.$userid.'" AND `thin` = "0"';
        $interval = 24+1;
        $formatString = 'Y-m-d H:00:00';
        $thinUpdate = '1';
      } else {
        $sqlNoThin = '`userid` = "'.$userid.'" AND `thin` = "1"';
        $interval = 168+24;
        $formatString = 'Y-m-d 00:00:00';
        $thinUpdate = '24';
      }
      // search the newest one where thinnig has not yet been applied (and is older than 25h)
      $sql = 'SELECT `zeit` FROM `verbrauch` WHERE '.$sqlNoThin.' AND `zeit` > DATE_SUB(NOW(), INTERVAL '.$interval.' HOUR) ORDER BY `id` ASC LIMIT 1;';
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry older than 25h, there is nothing to do. NB: there is a difference between NOW and last-insert-time
        return;
      }
      $row = $result->fetch_assoc();

      // compact all from the last hour before this entry
      $zeit = date_create($row['zeit']); // e.g. 18:43
      $zeitHourAlignedString = $zeit->format($formatString); // start of the last hour, e.g. 18:00

      // get the last one where thinning was not yet applied
      $result = $dbConn->query('SELECT `id` FROM `verbrauch` WHERE '.$sqlNoThin.' AND `zeit` < "'.$zeitHourAlignedString.'" ORDER BY `id` ASC LIMIT 1;');
      $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
      $idToUpdate = $row['id'];
      
      $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`consNtDiff`) as `sumConsNtDiff`, SUM(`consHtDiff`) as `sumConsHtDiff`, SUM(`genDiff`) as `sumGenDiff`, ';
      $sql .= 'SUM(`genNtDiff`) as `sumGenNtDiff`, SUM(`genHtDiff`) as `sumGenHtDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
      $sql = $sql. ' WHERE '.$sqlNoThin.' AND `zeit` < "'.$zeitHourAlignedString.'";';
      $result = $dbConn->query($sql);
      $row = $result->fetch_assoc();
    
      // now do the update and then delete the others
      $sql = 'UPDATE `verbrauch` SET `consDiff` = "'.$row['sumConsDiff'].'", `consNtDiff` = "'.$row['sumConsNtDiff'].'", `consHtDiff` = "'.$row['sumConsHtDiff'].'", ';
      $sql .= '`genDiff` = "'.$row['sumGenDiff'].'", `genNtDiff` = "'.$row['sumGenNtDiff'].'", `genHtDiff` = "'.$row['sumGenHtDiff'].'", ';
      $sql .= '`zeitDiff` = "'.$row['sumZeitDiff'].'", `thin` = "'.$thinUpdate.'" WHERE `id` = "'.$idToUpdate.'";';
      $result = $dbConn->query($sql);
            
      $sql = 'DELETE FROM `verbrauch` WHERE '.$sqlNoThin.' AND `zeit` < "'.$zeitHourAlignedString.'";';
      $result = $dbConn->query($sql);
    }

    function doDbThinning($dbConn, int $userid):void {
      // doing the thinning in 2 steps
      // - everything older than 24hours thin to 1 meas per hour: thin = 1 (hour)
      // - everything older than 72hours thin to 1 meas per day: thin = 24 (hour)

      // do so in a way the remaining data point after thinning is the first in his period, meaning the first datapoint of a day has always a timestamp of 00:00 or 00:01...
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:TRUE);
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:FALSE);
    }

    // copies the newest set into the archive db (where no thinning is executed)
    function copyToArchive ($dbConn, $rowId):void {      
      $sql =  'INSERT INTO `verbrauchArchive` ';
      $sql .= '(`userid`, `consumption`, `consDiff`, `consNt`, `consNtDiff`, `consHt`, `consHtDiff`, `gen`, `genDiff`, `genNt`, `genNtDiff`, `genHt`, `genHtDiff`, `zeit`, `zeitDiff`) ';
      $sql .= 'SELECT `userid`, `consumption`, `consDiff`, `consNt`, `consNtDiff`, `consHt`, `consHtDiff`, `gen`, `genDiff`, `genNt`, `genNtDiff`, `genHt`, `genHtDiff`, `zeit`, `zeitDiff` ';
      $sql .= 'FROM `verbrauch` WHERE `id` = "'.$rowId.'";';
      $result = $dbConn->query($sql);
    }

    $userid = checkInputs($dbConn);

    $sqlSafe_ir_answer = sqlSafeStrFromPost($dbConn, 'ir_answer', 511); // safe to insert into sql (not to output on html)
    // interested in total_consumption param (unfortunately no 16.7 and no cosPhi param. So phase-values are just indicative)
    $values = getInterestingValues($sqlSafe_ir_answer);
    if (! $values[0]) {
        printRawErrorAndDie('Error', 'values not found');
    }

    if (! $result = $dbConn->query('INSERT INTO `verbrauch` (`userid`, `consumption`, `consNt`, `consHt`, `gen`, `genNt`, `genHt`) VALUES ("'.$userid.'", "'.$values[1].'", "'.$values[2].'", "'.$values[3].'", "'.$values[4].'", "'.$values[5].'", "'.$values[6].'")')) {
        printRawErrorAndDie('Error', 'db insert not ok');
    }

    //NB: not using last inserted ID as other inserts may have happened in the meantime
    $result = $dbConn->query('SELECT * FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 2');
    $queryCount = $result->num_rows; // this may be 1 or 2
    if ($queryCount === 2) {
        $row_now = $result->fetch_assoc();
        $row_before = $result->fetch_assoc();
        $valueDiffsSql = getDiffs(row_now:$row_now, row_before:$row_before);
        $zeitDiff = date_diff(date_create($row_before['zeit']), date_create($row_now['zeit']));
        $zeitSecs = ($zeitDiff->d * 24 * 3600) + ($zeitDiff->h*3600) + ($zeitDiff->i * 60) + ($zeitDiff->s);
        
        $result = $dbConn->query('UPDATE `verbrauch` SET '.$valueDiffsSql.'`zeitDiff` = "'.$zeitSecs.'" WHERE `id` = "'.$row_now['id'].'";');

        copyToArchive(dbConn:$dbConn, rowId:$row_now['id']);
        
        // dbThinnings: do not need to run every time but it doesn't hurt either        
        doDbThinning(dbConn:$dbConn, userid:$userid);
         
    } else {
        echo 'no previous data'; // not an error
    }
    
?>