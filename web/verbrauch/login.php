<?php declare(strict_types=1); 
require_once('functions.php');

function sessionAndCookieDelete (): void {
    $_SESSION['userid'] = 0; // the most important one, make sure it's really 0
    setcookie('userIdCookie', '0', (time() - 42000), '/verbrauch/', 'strommesser.ch', true, true); // some big enough value in the past to make sure things like summer time changes do not affect it  
}

function processLoginData(object $dbConn, string $emailUnsafe, string $passwordUnsafe, int $setCookieSafe): bool {
    if (!(filter_var($emailUnsafe, FILTER_VALIDATE_EMAIL))) { // have a valid email
        printErrorAndDie('Error','Email ungültig');      
    }
    /* $userid = mail2userid($dbConn, $emailUnsafe); 
    if (!($userid > 0) ) { // email found in db
      printConfirm($dbConn, 'Error',getLanguage($dbConn,137,'wrong password').'<a href="index.php#login">login</a>');
      return false; 
    } */
    $userid = 1; // TODO

    if (!(verifyCredentials($dbConn, TRUE, $userid, $passwordUnsafe, ''))) { // verification ok
      return false; // This already prints an error message
    }
    if ($setCookieSafe === 1) {
      $expire = time() + (3600 * 24 * 7 * 52); // valid for a year
      setcookie('userIdCookie', (string)$userid, $expire, '/verbrauch/', 'strommesser.ch', true, true);

      // this is just a random number which has been set at user creation. To make sure one cannot read out others data by changing its cookie
      if (!($result = $dbConn->query('SELECT `randCookie` FROM `user` WHERE `id` = "'.$userid.'"' ))) {
        return error(110400); 
      }
      $row = $result->fetch_row();
      setcookie('randCookie', $row[0], $expire, '/verbrauch/', 'strommesser.ch', true, true);
    } // setCookie is selected
    redirectRelative('index.php');
    return true;
}

  // function to do the login. Several options are available to log in
  function verifyCredentials (object $dbConn, bool $authMethodPw, int $userid, $passwordUnsafe, $randCookieInput) : bool {
    $_SESSION['userid'] = 0; // clear it just to make sure    
    
    if (!($result = $dbConn->query('SELECT `pwHash`, `randCookie` FROM `user` WHERE `id` = "'.$userid.'"'))) {
      return error(112004);
    }
    if (!($result->num_rows == 1)) {
      return error(112003);
    }

    $row = $result->fetch_assoc();        
    $pwHash = $row['pwHash'];
    $randCookie = $row['randCookie'];
    
    if ($authMethodPw) { // with a pw
      if (!(($userid === 1) or (password_verify($passwordUnsafe, $pwHash)))) {
        printErrorAndDie('Error','falsches Passwort');
        return false;        
      } 
    } else { // with a Cookie
      if (!(($randCookie) and ($randCookie == $randCookieInput))) { // there is no zero in the data base and 64hex value is correct
        return error(112001);
      }
    }    
    if (!($dbConn->query('UPDATE `user` SET `lastLogin` = CURRENT_TIMESTAMP WHERE `id` = "'.$userid.'"'))) {
      return error(112005);
    }
    $_SESSION['userid'] = $userid;
    return true;
  } // function


function printBeginOfPage(string $head):void { // does not print the nav menu
    echo '<!DOCTYPE html><html><head>
    <meta charset="utf-8" />
    <title>StromMesser Log in</title>
    <meta name="description" content="zeigt deinen Energieverbrauch" />  
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="css/verbrauch.css" type="text/css" />
    </head><body>
    <div class="section noBottom">
    <div class="container">
    <h1>'.$head.'</h1>
    <div class="row twelve columns">&nbsp;</div>';
    return;
}
  
$doSafe = safeIntFromExt('GET', 'do', 1); // this is an integer (range 1 to 9) or non-existing
// do = 0: entry point
// do = 1: process login form
// do = 2: logout

if ($doSafe === 0) {
    printBeginOfPage('Log in');
    echo '   
    <form action="login.php?do=1" method="post">
    <div class="row">
        <div class="six columns" style="text-align: right">Email: </div>
        <div class="six columns" style="text-align: left"><input name="email" type="email" maxlength="127" value="" required size="20"></div>
    </div>    
    <div class="row" id="pwRow">
        <div class="six columns" style="text-align: right">Passwort: </div>
        <div class="six columns" style="text-align: left"><input name="password" type="password" maxlength="63" value="" required size="20"></div>
    </div>
    <div class="row twelve columns" style="font-size: smaller;"><input type="checkbox" name="setCookie" value="1" checked>auf diesem Gerät speichern</div>
    <div class="row twelve columns">&nbsp;</div>
    <div class="row twelve columns"><input name="create" type="submit" value="log in"></div>
    <div class="row twelve columns">&nbsp;</div>
    <div class="row twelve columns">&nbsp;</div>
    </form>';
} elseif ($doSafe === 1) {
    $emailUnsafe = filter_var(safeStrFromExt('POST', 'email', 127), FILTER_SANITIZE_EMAIL);    // email string, max length 127    
    $passwordUnsafe = filter_var(safeStrFromExt('POST', 'password', 63), FILTER_SANITIZE_STRING); // generic string, max length 63    
    $setCookieSafe = safeIntFromExt('POST', 'setCookie', 1);  
    processLoginData($dbConn, $emailUnsafe, $passwordUnsafe, $setCookieSafe); // this redirects on success 
} elseif ($doSafe === 2) {
    sessionAndCookieDelete();
    printBeginOfPage('Log out');    
    echo '<div class="row twelve columns">log out ok, zurück zur <a href="../wp/index.php">Startseite</a></div>';
} else {
    printErrorAndDie('Error','unsupported do on login.php');
}
?>
<div class="row twelve columns">&nbsp;</div>
</div></div></body></html>
