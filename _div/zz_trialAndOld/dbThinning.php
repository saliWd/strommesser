<?php declare(strict_types=1); 
    require_once 'functions.php';
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions

    function doReduction($dbConn, int $userid, bool $smlTimeScale):void {
      $tableName = 'verbrauch';
      report(dbConn:$dbConn, userid:$userid,tableName:$tableName);
      
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
      $sql = "SELECT `zeit` FROM `$tableName` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR) ORDER BY `id` ASC LIMIT 1;";
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
      $sql = "SELECT `id` FROM `$tableName` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` >= \"$zeitAlignedPlusStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        return;
      }

      $sql = "SELECT `id` FROM `$tableName` WHERE $sqlNoThin AND `zeit` < DATE_SUB(NOW(), INTERVAL $interval HOUR)";
      $sql .= " AND `zeit` < \"$zeitAlignedPlusStr\" AND `zeit` >= \"$zeitAlignedStr\"";
      $sql .= " ORDER BY `id` ASC LIMIT 1;";
      $result = $dbConn->query($sql);
      if ($result->num_rows < 1) { // if there is no entry within this hour, there is nothing to do
        return;
      }

      $row = $result->fetch_assoc();   // -> gets me the ID I want to update with the next commands
      $idToUpdate = $row['id']; // oldest one      

      $sql = 'SELECT SUM(`consDiff`) as `sumConsDiff`, SUM(`consNtDiff`) as `sumConsNtDiff`, SUM(`consHtDiff`) as `sumConsHtDiff`, SUM(`genDiff`) as `sumGenDiff`,';
      $sql .= " SUM(`genNtDiff`) as `sumGenNtDiff`, SUM(`genHtDiff`) as `sumGenHtDiff`, SUM(`zeitDiff`) as `sumZeitDiff` FROM `$tableName`";
      $sql .= " WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      $result = $dbConn->query($sql);
      $row = $result->fetch_assoc();
    
      // now do the update and then delete the others
      $sql = 'UPDATE `'.$tableName.'` SET `consDiff` = "'.$row['sumConsDiff'].'", `consNtDiff` = "'.$row['sumConsNtDiff'].'", `consHtDiff` = "'.$row['sumConsHtDiff'].'",';
      $sql .= ' `genDiff` = "'.$row['sumGenDiff'].'", `genNtDiff` = "'.$row['sumGenNtDiff'].'", `genHtDiff` = "'.$row['sumGenHtDiff'].'",';
      $sql .= ' `zeitDiff` = "'.$row['sumZeitDiff'].'", `thin` = "'.$thinUpdate.'" WHERE `id` = "'.$idToUpdate.'";';
      $result = $dbConn->query($sql);
            
      $sql = "DELETE FROM `$tableName` WHERE $sqlNoThin AND `zeit` < \"$zeitAlignedPlusStr\";";
      $result = $dbConn->query($sql);
      report(dbConn:$dbConn, userid:$userid,tableName:$tableName);
    }
    function report($dbConn, int $userid, string $tableName):void {
      echo 'report<br>';
      $result = $dbConn->query("SELECT `id` FROM `$tableName` WHERE `userid` = $userid;");
      $numTot = $result->num_rows;
      $result = $dbConn->query("SELECT `id` FROM `$tableName` WHERE `userid` = $userid AND `thin` = 0;");
      $numThin_0 = $result->num_rows;
      $result = $dbConn->query("SELECT `id` FROM `$tableName` WHERE `userid` = $userid AND `thin` = 1;");
      $numThin_1 = $result->num_rows;
      $result = $dbConn->query("SELECT `id` FROM `$tableName` WHERE `userid` = $userid AND `thin` = 24;");
      $numThin_24 = $result->num_rows;

      echo "Numbers:<br>Tot:$numTot<br>Thin_0:$numThin_0<br>Thin_1:$numThin_1<br>Thin_24:$numThin_24<br>";
    }

    function doDbThinning($dbConn, int $userid):void {
      // doing the thinning in 2 steps
      // - everything older than 24hours thin to 1 meas per hour: thin = 1 (hour)
      // - everything older than 72hours thin to 1 meas per day: thin = 24 (hour)

      // do so in a way the remaining data point after thinning is the first in his period, meaning the first datapoint of a day has always a timestamp of 00:00 or 00:01...
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:TRUE);
      doReduction(dbConn:$dbConn, userid:$userid, smlTimeScale:FALSE);
    }

    $userid = 1;
    doDbThinning(dbConn:$dbConn, userid:$userid);
    echo '<br><br>';
    $userid = 2;
    doDbThinning(dbConn:$dbConn, userid:$userid);


