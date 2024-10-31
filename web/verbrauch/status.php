<?php declare(strict_types=1); 
    require_once 'functions.php';
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions
    
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
            $output .= "userid: $userid is invalid<br>\n";
        }
    }

    $okOrNot = $valid === count($userids_to_check);
    
    $cronjob = safeIntFromExt(source:'GET',varName:'cronjob',length:1);
    if ($cronjob === 1) {
        if (!$okOrNot) {
            $mailSendOk = mail(
                to:'messer@strommesser.ch;',
                subject:'Status Strommesser cronjob: nicht ok',
                message:'Statusemail: Status ist nicht ok. Schau direkt nach: <a href=\"https://strommesser.ch/verbrauch/status.php\">Statuspage</a>'
            );
            if (!$mailSendOk) {
                echo 'Es ist ein Fehler passiert beim Versenden der Email'; // nobody sees this when running the cronjob automatically
            }
        } else {
            echo 'Status ist ok, es wird keine Mail verschickt'; // nobody sees this when running the cronjob automatically
        }
    } else {
        printBeginOfPage_v2(site:'status.php', title:'Status');
        $okOrNotTxt = $okOrNot ? 'ok' : '<span class="text-xl text-red-600">nicht ok</span>';
        echo '
    <div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Status ist '.$okOrNotTxt.'</h3>
    <p class="font-normal text-gray-700">'.$output.'</p>
    </div>
    </div></body></html>';
    }