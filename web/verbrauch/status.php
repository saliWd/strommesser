<?php declare(strict_types=1); 
    require_once('functions.php');
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    // expecting a call like "<file>.php"
    
    $zeitNow = date_create("now");
    $zeitNow->modify('- 5 minutes'); // latest entry must be newer than '5 minutes ago'
    $valid = 0;

    $userids_to_check = [1, 2]; // might get that from DB as well. Need to exclude test account and non-active ones though
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

    $nice = safeIntFromExt(source:'GET',varName:'nice',length:1);
    if ($nice === 0) {
        echo ($valid === count($userids_to_check)) ? '1' : '0';
        if ($output) {
            echo "<br>\n".$output;
        }
    } else {
        printBeginOfPage_v2(site:'status.php', title:'Status');
        $okOrNot = ($valid === count($userids_to_check)) ? 'ok' : '<span class="text-xl text-red-600">nicht ok</span>';
        echo '
<div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Status ist '.$okOrNot.'</h3>
  <p class="font-normal text-gray-700">'.$output.'</p>
</div>
</div></body></html>';       
    }
?>