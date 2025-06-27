<?php declare(strict_types=1); 
    require_once 'functions.php';
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions    

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
      // 



      if ($smlTimeScale) {
        $sqlNoThin = '`userid` = '.$userid.' AND `thin` = 0';
        $interval = 25; // hours. Age before doing compacting
        $formatString = 'Y-m-d H:00:00';
        $thinUpdate = '1';
        $modifier = '+1 hour';
      } else {
        $sqlNoThin = '`userid` = '.$userid.' AND `thin` = 1';
        $interval = 192; // 168+24;
        $formatString = 'Y-m-d 00:00:00';
        $thinUpdate = '24';
        $modifier = '+1 day';
      }
      // search the oldest one where thinnig has not yet been applied (and is older than 25h)
      $sql = "SELECT `zeit` FROM `verbrauch_tmp` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR) ORDER BY `id` ASC LIMIT 1;";
      echo "$sql<br>";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry older than 25h, there is nothing to do. NB: there is a difference between NOW and last-insert-time
        echo "no older entry than $interval h <br>";
        return;
      }
      $row = $result->fetch_assoc();

      // compact all from the last hour before this entry
      $zeit = date_create(datetime: $row['zeit']); // e.g. 18:43
      $zeitAligned = date_create(datetime: $zeit->format(format: $formatString)); // start of the last hour, e.g. 18:00
      $zeitAlignedStr = $zeitAligned->format(format: $formatString); // as string: 19:00
      $zeitAlignedPlus = $zeitAligned->modify(modifier: $modifier); // go one hour/day further, 19:00
      $zeitAlignedPlusStr = $zeitAlignedPlus->format(format: $formatString); // as string: 19:00
      $zeitAlignedPlusPlus = $zeitAligned->modify(modifier: $modifier); // go one hour/day further, 20:00
      $zeitAlignedPlusPlusStr = $zeitAlignedPlusPlus->format(format: $formatString); // as string: 20:00

      echo 'zeit: '.$zeit->format(format: 'Y-m-d H:i:s').'<br>';      
      echo "zeitAligned: $zeitAlignedStr <br>";
      echo "zeitAlignedPlus: $zeitAlignedPlusStr <br>";
      echo "zeitAlignedPlusPlus: $zeitAlignedPlusPlusStr <br>";

      // check whether this one is still old enough and thinning is ok
      $sql = "SELECT `id`,`zeit` FROM `verbrauch_tmp` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` < \"$zeitAlignedPlusPlusStr\" AND `zeit` >= \"$zeitAlignedPlusStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      echo "$sql<br>";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        echo 'no entry between zeitAlignedPlus and zeitAlignedPlusPlus<br>';
        return;
      }

      $sql = "SELECT `id`,`zeit` FROM `verbrauch_tmp` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` < \"$zeitAlignedPlusStr\" AND `zeit` >= \"$zeitAlignedStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      echo "$sql<br>";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        echo 'no entry between zeitAligned and zeitAlignedPlus<br>';
        return;
      }

      $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
      $idToUpdate = $row['id']; // oldest one      

      $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`consNtDiff`) as `sumConsNtDiff`, SUM(`consHtDiff`) as `sumConsHtDiff`, SUM(`genDiff`) as `sumGenDiff`, ';
      $sql .= 'SUM(`genNtDiff`) as `sumGenNtDiff`, SUM(`genHtDiff`) as `sumGenHtDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `verbrauch_tmp`';
      $sql .= " WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      echo "$sql<br>";
      $result = $dbConn->query($sql);
      $row = $result->fetch_assoc();
    
      // now do the update and then delete the others
      $sql = 'UPDATE `verbrauch_tmp` SET `consDiff` = "'.$row['sumConsDiff'].'", `consNtDiff` = "'.$row['sumConsNtDiff'].'", `consHtDiff` = "'.$row['sumConsHtDiff'].'", ';
      $sql .= '`genDiff` = "'.$row['sumGenDiff'].'", `genNtDiff` = "'.$row['sumGenNtDiff'].'", `genHtDiff` = "'.$row['sumGenHtDiff'].'", ';
      $sql .= '`zeitDiff` = "'.$row['sumZeitDiff'].'", `thin` = "'.$thinUpdate.'" WHERE `id` = "'.$idToUpdate.'";';
      echo "$sql<br>";
      $result = $dbConn->query($sql);
            
      $sql = "DELETE FROM `verbrauch_tmp` WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      echo "$sql<br>";
      $result = $dbConn->query($sql);
    }

    function doDbThinning($dbConn, int $userid):void {
      report(dbConn:$dbConn, userid:$userid);
      echo '<br>doing reduction thin=1<br>';
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:TRUE);      
      report(dbConn:$dbConn, userid:$userid);
      
      echo '<br>doing reduction thin=24<br>';
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:FALSE);
      report(dbConn:$dbConn, userid:$userid);
      
    }

    function report($dbConn, int $userid):void {
      echo 'report<br>';
      $sql = 'SELECT `id` FROM `verbrauch_tmp` WHERE `userid` = '.$userid.';';
      $result = $dbConn->query($sql);
      $numTot = $result->num_rows;
      $sql = 'SELECT `id` FROM `verbrauch_tmp` WHERE `userid` = '.$userid.' AND `thin` = 0;';
      $result = $dbConn->query($sql);
      $numThin_0 = $result->num_rows;
      $sql = 'SELECT `id` FROM `verbrauch_tmp` WHERE `userid` = '.$userid.' AND `thin` = 1;';
      $result = $dbConn->query($sql);
      $numThin_1 = $result->num_rows;
      $sql = 'SELECT `id` FROM `verbrauch_tmp` WHERE `userid` = '.$userid.' AND `thin` = 24;';
      $result = $dbConn->query($sql);
      $numThin_24 = $result->num_rows;

      echo 'Numbers:<br>Tot:'.$numTot.'<br>Thin_0:'.$numThin_0.'<br>Thin_1:'.$numThin_1.'<br>Thin_24:'.$numThin_24.'<br>';
    }

    $userid = 1;

    //NB: not using last inserted ID as other inserts may have happened in the meantime
    $result = $dbConn->query('SELECT * FROM `verbrauch_tmp` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 2');
    $queryCount = $result->num_rows; // this may be 1 or 2
    if ($queryCount === 2) {
        $row_now = $result->fetch_assoc();
        $row_before = $result->fetch_assoc();
        $valueDiffsSql = getDiffs(row_now:$row_now, row_before:$row_before);
        $zeitDiff = date_diff(baseObject: date_create(datetime: $row_before['zeit']), targetObject: date_create(datetime: $row_now['zeit']));
        $zeitSecs = $zeitDiff->d*24*3600 + $zeitDiff->h*3600 + $zeitDiff->i*60 + $zeitDiff->s;
        
       
        // dbThinnings: do not need to run every time but it doesn't hurt either                
        doDbThinning(dbConn:$dbConn, userid:$userid);        
    }

