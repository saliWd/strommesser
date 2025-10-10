<?php declare(strict_types=1); 
    require_once 'functions.php';
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "https://strommesser.ch/verbrauch/pico2w_v3.php?TX=pico&TXVER=3"
    // with POST data (url encoded)

    function getDiffs($row_now, $row_before):string {
      // `cons`    -> `consDiff`
      // `consNt`  -> `consNtDiff`
      // `consHt`  -> `consHtDiff`
      // `gen`     -> `genDiff`
      $sqlString = '';
      $sqlString .= '`consDiff` = "'.($row_now['cons'] - $row_before['cons']).'", ';
      $sqlString .= '`consNtDiff` = "'.($row_now['consNt'] - $row_before['consNt']).'", '; 
      $sqlString .= '`consHtDiff` = "'.($row_now['consHt'] - $row_before['consHt']).'", '; 
      $sqlString .= '`genDiff` = "'.($row_now['gen'] - $row_before['gen']).'", '; 
    
      return $sqlString;
    }

    function doReduction($dbConn, int $userid, bool $smlTimeScale):void {
      // possible issues: 
      // - can have lots of entries per hour because of fast readout (usually 30)
      // - can have only few entries per hour because of breaks or slower readout
      if ($smlTimeScale) {
        $sqlNoThin = "`userid` = $userid AND `thin` = 0";
        $interval = 25; // hours. Age before doing compacting
        $formatString = 'Y-m-d H:00:00';
        $thinUpdate = '1';
        $modifier = '+1 hour';
      } else {
        $sqlNoThin = "`userid` = $userid AND `thin` = 1";
        $interval = 192; // 8 days;
        $formatString = 'Y-m-d 00:00:00';
        $thinUpdate = '24';
        $modifier = '+1 day';
      }
      // search the oldest one where thinnig has not yet been applied (and is older than 25h)
      $sql = "SELECT `zeit` FROM `verbrauch` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR) ORDER BY `id` ASC LIMIT 1;";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry older than 25h, there is nothing to do. NB: there is a difference between NOW and last-insert-time
        return;
      }
      $row = $result->fetch_assoc();

      // compact all from the last hour before this entry
      $zeit = date_create(datetime: $row['zeit']); // e.g. 18:43
      $zeitAligned = date_create(datetime: $zeit->format(format: $formatString)); // start of the last hour, e.g. 18:00
      $zeitAlignedStr = $zeitAligned->format(format: $formatString); // as string: 19:00
      $zeitAlignedPlus = $zeitAligned->modify(modifier: $modifier); // go one hour/day further, 19:00
      $zeitAlignedPlusStr = $zeitAlignedPlus->format(format: $formatString); // as string: 19:00
      
      // check whether this one is still old enough and thinning is ok
      $sql = "SELECT `id` FROM `verbrauch` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` >= \"$zeitAlignedPlusStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        return;
      }

      $sql = "SELECT `id` FROM `verbrauch` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` < \"$zeitAlignedPlusStr\" AND `zeit` >= \"$zeitAlignedStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        return;
      }

      $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
      $idToUpdate = $row['id']; // oldest one

      $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`consNtDiff`) as `sumConsNtDiff`, SUM(`consHtDiff`) as `sumConsHtDiff`, SUM(`genDiff`) as `sumGenDiff`,';
      $sql .= ' SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch`';
      $sql .= " WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      $result = $dbConn->query($sql);
      $row = $result->fetch_assoc();
    
      // now do the update and then delete the others
      $sql = 'UPDATE `verbrauch` SET `consDiff` = "'.$row['sumConsDiff'].'", `consNtDiff` = "'.$row['sumConsNtDiff'].'", `consHtDiff` = "'.$row['sumConsHtDiff'].'",';
      $sql .= ' `genDiff` = "'.$row['sumGenDiff'].'",';
      $sql .= ' `zeitDiff` = "'.$row['sumZeitDiff'].'", `thin` = "'.$thinUpdate.'" WHERE `id` = "'.$idToUpdate.'";';
      $result = $dbConn->query($sql);
            
      $sql = "DELETE FROM `verbrauch` WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      $result = $dbConn->query($sql);
    }

    // copies the newest set into the archive db (where no thinning is executed)
    function copyToArchive ($dbConn, $rowId):void {
      $sql =  'INSERT INTO `verbrauchArchive` ';
      $sql .= '(`userid`, `cons`, `consDiff`, `consNt`, `consNtDiff`, `consHt`, `consHtDiff`, `gen`, `genDiff`, `zeit`, `zeitDiff`) ';
      $sql .= 'SELECT `userid`, `cons`, `consDiff`, `consNt`, `consNtDiff`, `consHt`, `consHtDiff`, `gen`, `genDiff`, `zeit`, `zeitDiff` ';
      $sql .= "FROM `verbrauch` WHERE `id` = \"$rowId\";";
      $dbConn->query($sql);
    }

    $userid = checkInputs(dbConn: $dbConn);

    $sqlSafe_values = sqlSafeStrFromPost(dbConn: $dbConn, varName: 'values', length: 255); // safe to insert into sql (not to output on html)
    // meas_string = meas['date_time']+'|'+meas['energy_pos']+'|'+meas['energy_neg']+'|'+meas['energy_pos_t1']+'|'+meas['energy_pos_t2']

    $values = explode(separator: '|',string: $sqlSafe_values, limit: 5);
    if (! $values[0]) {
        printRawErrorAndDie(heading: 'Error', text: 'values not found');
    }

    // NB: previously egs had a different Ht and Nt definition
    if (! $result = $dbConn->query(query:'INSERT INTO `verbrauch` (`userid`, `cons`, `gen`, `consHt`, `consNt`) VALUES ("'.$userid.'", "'.$values[1].'", "'.$values[2].'", "'.$values[3].'", "'.$values[4].'")')) {
        printRawErrorAndDie(heading: 'Error', text: 'db insert not ok');
    }


    //NB: not using last inserted ID as other inserts may have happened in the meantime
    $result = $dbConn->query(query: "SELECT * FROM `verbrauch` WHERE `userid` = \"$userid\" ORDER BY `id` DESC LIMIT 2");
    $queryCount = $result->num_rows; // this may be 1 or 2
    if ($queryCount === 2) {
        $row_now = $result->fetch_assoc();
        $row_before = $result->fetch_assoc();
        $valueDiffsSql = getDiffs(row_now:$row_now, row_before:$row_before);
        $zeitDiff = date_diff(baseObject: date_create(datetime: $row_before['zeit']), targetObject: date_create(datetime: $row_now['zeit']));
        $zeitSecs = $zeitDiff->d*24*3600 + $zeitDiff->h*3600 + $zeitDiff->i*60 + $zeitDiff->s;
        
        $result = $dbConn->query(query: 'UPDATE `verbrauch` SET '.$valueDiffsSql.'`zeitDiff` = "'.$zeitSecs.'" WHERE `id` = "'.$row_now['id'].'";');

        copyToArchive(dbConn:$dbConn, rowId:$row_now['id']);
        
        // dbThinnings: do not need to run every time but it doesn't hurt either
        doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:TRUE);
        doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:FALSE);
    }

    // check whether the last measurement is not older than 5 mins and 
    // get the settings and output them on the screen
    $result = $dbConn->query(query: "SELECT `zeit` FROM `verbrauch` WHERE `userid` = \"$userid\" ORDER BY `id` DESC LIMIT 1;");
    $queryCount = $result->num_rows; // this may be 0 or 1
    if ($queryCount !== 1) {
        printRawErrorAndDie(heading: 'Error', text: 'no meas data');
    }
    $row = $result->fetch_assoc();

    $zeitNewest = date_create(datetime: $row['zeit']);
    $zeitNow = date_create(datetime: 'now');
    $zeitNow->modify(modifier: '- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    $serverOk = ($zeitNewest > $zeitNow) ? 1:0;
    
    $result = $dbConn->query(query: "SELECT `priceConsHt`,`priceConsNt`,`priceGen`,`ledMinValCon`,`ledMaxValGen`,`ledBrightness` FROM `kunden` WHERE `id` = \"$userid\" LIMIT 1;");
    if ($result->num_rows !== 1) {
        printRawErrorAndDie(heading: 'Error', text: 'no config data');
    } 
    $rowKunden = $result->fetch_assoc();


    // get daily costs
    $zeitNewest = date_create(datetime: 'now');
    $zeitOldestString = $zeitNewest->format(format: 'Y-m-d 00:00:00'); // beginning of the current day
 
    $sql = "SELECT `gen`, `consNt`, `consHt` from `verbrauch` WHERE `userid` = \"$userid\" AND `zeit` > \"$zeitOldestString\" ORDER BY `zeit` DESC;";
    $result = $dbConn->query(query:$sql);
    $result->data_seek(offset: $result->num_rows - 1); // skip to the last entry of the rows
    $rowOldest = $result->fetch_assoc();
    $result->data_seek(offset:0); // go back to the first row
    $row = $result->fetch_assoc();

    $earn = -1.0 * 
                (($row['consNt'] - $rowOldest['consNt'])*$rowKunden['priceConsNt'] +
                ($row['consHt'] - $rowOldest['consHt'])*$rowKunden['priceConsHt'] -
                ($row['gen'] - $rowOldest['gen'])*$rowKunden['priceGen']);
    $earn = round(num:$earn,precision:2);
    
    // serverok|ledBrightness|ledMinValCon|ledMaxValGen|earn
    echo $serverOk.'|'.$rowKunden['ledBrightness'].'|'.$rowKunden['ledMinValCon'].'|'.$rowKunden['ledMaxValGen'].'|'.$earn;




