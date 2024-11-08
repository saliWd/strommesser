<?php declare(strict_types=1); 
    require_once 'functions.php';

    
    // checks wether the last measurement for each id is less than 5mins old
    function checkStatus($dbConn): array {
        $userids_to_check = [1, 2]; // might get that from DB as well. Need to exclude test account and non-active ones though
        $zeitNow = date_create("now");
        $zeitNow->modify('- 5 minutes'); // latest entry must be newer than '5 minutes ago'
        $validIds = 0;

        $output = '';
        foreach ($userids_to_check as $userid) {
            $result = $dbConn->query('SELECT `zeit` FROM `verbrauch` WHERE `userid` = "'.$userid.'" ORDER BY `id` DESC LIMIT 1');
            if ($result->num_rows !== 1) {
                break;
            } 
            $row = $result->fetch_assoc();
            $zeitNewest = date_create($row['zeit']);   
            if ($zeitNewest > $zeitNow) {
                $validIds++;
            } else {
                $output .= "userid: $userid is invalid<br>\n";
            }
        }
        $okOrNot = $validIds === count($userids_to_check);
        return [$okOrNot, $output];
    }
  
    $dbConn = get_dbConn(); // do not use initialize as I don't use sessions        
    [$okOrNot, $output] = checkStatus(dbConn: $dbConn);
        
    
    $cronjob = safeIntFromExt(source:'GET',varName:'cronjob',length:1);
    if ($cronjob === 1) {
        $okOrNotInteger = $okOrNot ? 1 : 0;
        if (!($dbConn->query(query: "INSERT INTO `status` (`ok`) VALUES ($okOrNotInteger)"))) { // other fields are auto generated
            echo 'db insert hat leider nicht funktioniert...';
          }
        if (!$okOrNot) {
            $mailSendOk = mail(
                to:'messer@strommesser.ch;',
                subject:'Status Strommesser cronjob: nicht ok',
                message:'Statusemail: Status ist nicht ok. Schau direkt nach: https://strommesser.ch/verbrauch/status.php'
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

        $dbHistTxt = '<br><h4 class="mb-2 text-l font-bold tracking-tight text-gray-900">Status in den letzten 24 Stunden</h4>
        <p>';
        $result = $dbConn->query('SELECT `zeit`, `ok` FROM `status` WHERE 1 ORDER BY `id` DESC LIMIT 24'); // last 24 entries
        while ($row = $result->fetch_assoc()) {
            $statusTxt = $row['ok'] == 1 ? '<span class="text-green-600">ok</span>' : '<span class="text-red-500 font-bold">nicht ok</span>';
            $zeitTxt = date_create($row['zeit'])->format(format: 'H:i d.m.Y');
            $dbHistTxt .= "Status ist $statusTxt ($zeitTxt)<br>";
        }
        $dbHistTxt .= '</p>';

        echo '
    <div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
    <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">Status ist '.$okOrNotTxt.'</h3>
    <p class="font-normal text-gray-700">'.$output.'</p>
    '.$dbHistTxt.'
    </div>
    </div></body></html>';
    }