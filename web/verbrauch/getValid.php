<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "<file>.php"
    
    $zeitNow = date_create("now");
    $zeitNow->modify('- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    $valid = 0;

    $userids_to_check = [1, 2];
    $output = '';
    foreach ($userids_to_check as $userid) {
        $result = $dbConn->query('SELECT `zeit` FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 1');
        if ($result->num_rows !== 1) {
            break;
        } 
        $row = $result->fetch_assoc();
        $zeitNewest = date_create($row['zeit']);   
        if ($zeitNewest > $zeitNow) {
            $valid++;
        } else {
            $output .= 'userid: '.$userid.' is invalid'."<br>\n";
        }
    }
    echo ($valid === count($userids_to_check)) ? '1' : '0';

    if ($output) {
        echo "<br>\n".$output;
    }
?>