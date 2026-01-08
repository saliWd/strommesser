<?php declare(strict_types=1);
require_once 'functions.php';
$dbConn = initialize();

// sql command on old db:
// SELECT * FROM `verbrauch` WHERE `zeit` < "2025-12-31 23:59:59" AND `copied` = 0 ORDER BY `id` DESC; 
// rates for 2025 are
// - HT:0.3318, NT:0.2718, GEN:0.1500
// rates for 2026 are
// - Winter: CON=0.2808, GEN=0.1200


// $sql = "SELECT * from `verbrauch` WHERE `zeit` < \"2025-12-31 23:59:59\" AND `copied` = 0 ORDER BY `id` DESC LIMIT 1"; 
$sql = 'SELECT * from `verbrauch` WHERE `userid` = "1" AND `copied` = 0 ORDER BY `id` DESC LIMIT 1';
$result = $dbConn->query(query:$sql);
$queryCount = $result->num_rows;

if ($queryCount > 0) {
    $rowOld = $result->fetch_assoc();
    
    /*
    if ($rowOld['consHtDiff']+$rowOld['consNtDiff'] > 0) { 
        $conRate = ($rowOld['consHtDiff']*0.3318 + $rowOld['consNtDiff']*0.2718) / ($rowOld['consHtDiff']+$rowOld['consNtDiff']);
    } else { // both are zero, it does not matter
       $conRate = 0.3318;
    }  
    */

    $sql =  'INSERT INTO `verbrauch_26` (`userid`, `con`, `conDiff`, `conRate`, `gen`, `genDiff`, `genRate`, `zeit`, `zeitDiff`, `thin`) VALUES (';
    //                                    '23', '', '', '', '', '', '', current_timestamp(), '', '0');
    $sql .= '"'.$rowOld['userid'].'", ';
    $sql .= '"'.$rowOld['cons'].'", ';
    $sql .= '"'.$rowOld['consDiff'].'", ';
    $sql .= "0.2808, "; // only winter data is copied // $sql .= "$conRate, ";
    $sql .= '"'.$rowOld['gen'].'", ';
    $sql .= '"'.$rowOld['genDiff'].'", ';
    $sql .= '"0.12", ';
    $sql .= '"'.$rowOld['zeit'].'", ';
    $sql .= '"'.$rowOld['zeitDiff'].'", ';
    $sql .= '"'.$rowOld['thin'].'");';

    echo "<br>sql:<br>$sql<br>";

    if ($dbConn->query(query: $sql)) {
        echo '<br>copied one data set<br>';
        $result = $dbConn->query(query: 'UPDATE `verbrauch` SET `copied` = "1" WHERE `id` = "'.$rowOld['id'].'";');
        if ($result) {
            echo '<br>update of old db did work<br>';
        } else {
            printRawErrorAndDie(heading: 'Error', text: 'update of old db did not work');    
        }
    } else {
        printRawErrorAndDie(heading: 'Error', text: 'copy did not work');
    }
} else {
    echo '<br>no old data found<br>';
}






