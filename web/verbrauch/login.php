<?php declare(strict_types=1); 
require_once('functions.php');
session_start(); // this code must precede any HTML output
$dbConn = get_dbConn(); // do not use initialize here

function sessionAndCookieDelete (): void {
  $_SESSION['userid'] = 0; // the most important one, make sure it's really 0
  setcookie('userIdCookie', '0', (time() - 42000), '/verbrauch/', 'strommesser.ch', TRUE, TRUE); // some big enough value in the past to make sure things like summer time changes do not affect it
}

// returns the userid which matches to the email given. Returns 0 if something went wrong
function mail2userid (object $dbConn, string $emailUnsafe) : int {    
  if (!($result = $dbConn->query('SELECT `id` FROM `user` WHERE `email` = "'.mysqli_real_escape_string($dbConn, $emailUnsafe).'";'))) {
    return 0;
  }
  if (!($result->num_rows == 1)) {
    return 0;
  }
  
  $row = $result->fetch_row();
  return (int)$row[0];            
}  

function processLoginData(object $dbConn, string $emailUnsafe, string $passwordUnsafe, int $setCookieSafe, bool $doRedirect=TRUE): bool {
  if (!(filter_var($emailUnsafe, FILTER_VALIDATE_EMAIL))) { // have a valid email
      printErrorAndDie('Error','Email ungültig');
      return FALSE;
  }

  $userid = mail2userid(dbConn:$dbConn, emailUnsafe:$emailUnsafe);
  if (!($userid > 0) ) { // email found in db
    printErrorAndDie('Error','Falsches Passwort oder Email... Nochmals versuchen? <a href="login.php" class="underline">zurück zur Login-Seite</a>');
    return FALSE; 
  } 

  if (!(verifyCredentials(dbConn:$dbConn, authMethodPw:TRUE, userid:$userid, passwordUnsafe:$passwordUnsafe))) { // verification ok
    return FALSE; // This already prints an error message
  }
  if ($setCookieSafe === 1) {
    $expire = time() + (3600 * 24 * 7 * 52); // valid for a year
    setcookie('userIdCookie', (string)$userid, $expire, '/verbrauch/', 'strommesser.ch', TRUE, TRUE);

    // this is just a random number which has been set at user creation. To make sure one cannot read out others data by changing its cookie
    if (!($result = $dbConn->query('SELECT `randCookie` FROM `user` WHERE `id` = "'.$userid.'"' ))) {
      return error(110400); 
    }
    $row = $result->fetch_row();
    setcookie('randCookie', $row[0], $expire, '/verbrauch/', 'strommesser.ch', TRUE, TRUE);
  } // setCookie is selected
  if ($doRedirect) {
    redirectRelative('index.php');
  }
  return TRUE;
}

// function to do the login. Several options are available to log in
function verifyCredentials (object $dbConn, bool $authMethodPw, int $userid=0, string $passwordUnsafe='', string $randCookieInput='') : bool {
  $_SESSION['userid'] = 0; // clear it just to make sure
  
  if (!($result = $dbConn->query('SELECT `pwHash`, `randCookie` FROM `user` WHERE `id` = "'.$userid.'"'))) {
    return error(112004);
  }
  if (!($result->num_rows === 1)) {
    return error(112003);
  }

  $row = $result->fetch_assoc();
  $pwHash = $row['pwHash'];
  $randCookie = $row['randCookie'];
  
  if ($authMethodPw) { // with a pw
    if (!(password_verify($passwordUnsafe, $pwHash))) {
      printErrorAndDie('Error','falsches Passwort');
      return FALSE;
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
  return TRUE;
} // function

function printLoginForm (string $reason, int $formDo, string $submitText): void {
  echo '
  <form action="login.php?do='.$formDo.'" method="post" id="loginForm">
    <div class="flex flex-row mt-8">
      <div class="basis-1/3 self-center">Email:</div>
      <div class="basis-2/3 inline-block align-middle"><input class="input-text" name="email" type="email" maxlength="127" value="" required></div>
    </div>';
    if ($reason === 'change' or $reason === 'login') {
      echo '    
    <div class="flex flex-row mt-2">
      <div class="basis-1/3 self-center">Passwort:</div>
      <div class="basis-2/3"><input class="input-text" name="password" type="password" maxlength="63" value="" required></div>
    </div>'; 
    }
    if ($reason === 'change') {
      echo '
    <div class="flex flex-row mt-2">
      <div class="basis-1/3 self-center">neues Passwort:</div>
      <div class="basis-2/3"><input class="input-text" name="passwordNew" type="password" maxlength="63" value="" required></div>
    </div>';
    } 
    if ($reason === 'login') { 
      echo '
    <div class="flex flex-row justify-center mt-2">
      <div><input class="w-10" type="checkbox" name="setCookie" value="1" checked> <span class="text-sm">auf diesem Gerät speichern</span></div>
    </div>';
    }
    echo '
    <div class="flex flex-row justify-center mt-2">
      <div><input id="loginFormSubmit" class="mt-8 input-text basis-full" name="submit" type="submit" value="'.$submitText.'"></div>
    </div>
  </form>
  <hr class="my-8">';
  if ($reason != 'change') {
    echo '
  <div class="flex flex-row justify-center">
    <div><a href="login.php?do=3" class="input-text basis-full">Passwort ändern</a></div>
  </div>';
  }
  if ($reason != 'forgot') {
    echo '
  <div class="flex flex-row justify-center mt-4">
    <div><a href="login.php?do=5" class="input-text basis-full">Passwort vergessen</a></div>
  </div>
  ';
  }
}

$doSafe = safeIntFromExt('GET', 'do', 1); // this is an integer (range 1 to 9) or non-existing
// do = 0: entry point
// do = 1: process login form
// do = 2: logout
// do = 3: present changePW form
// do = 4: execute the changePW
// do = 5: present the forgotPW form
// do = 6: TODO: execute the forgotPW

if ($doSafe === 0) {
  // check cookie
  $useridCookieSafe = safeIntFromExt('COOKIE', 'userIdCookie', 11);
  $randCookieSafe   = safeHexFromExt('COOKIE', 'randCookie', 64); 
  if (($useridCookieSafe > 0) and (verifyCredentials(dbConn:$dbConn, authMethodPw:FALSE, userid:$useridCookieSafe, randCookieInput:$randCookieSafe))){
    redirectRelative('index.php'); // always going back to the main page after login   
    die(); // will not be executed
  } // no cookie present and no userid. print the login form

  printBeginOfPage(site:'login.php', title:'Log in');
  printLoginForm(reason:'login', formDo:1, submitText:'Log in');
} elseif ($doSafe === 1) {
  processLoginData(
    dbConn:$dbConn,
    emailUnsafe:filter_var(safeStrFromExt('POST', 'email', 127), FILTER_SANITIZE_EMAIL), // email string, max length 127
    passwordUnsafe:filter_var(safeStrFromExt('POST', 'password', 63), FILTER_SANITIZE_STRING), // generic string, max length 63
    setCookieSafe:safeIntFromExt('POST', 'setCookie', 1)
  ); // this redirects on success
} elseif ($doSafe === 2) {
  sessionAndCookieDelete();
  printBeginOfPage(site:'login.php', title:'Log out');
  echo '<p>log out ok, zurück zur <a href="../index.php" class="underline">Startseite</a></p>';
} elseif ($doSafe === 3) {    
  printBeginOfPage(site:'login.php', title:'Passwort ändern');
  printLoginForm (reason:'change', formDo:4, submitText:'Neues Passwort speichern');
} elseif ($doSafe === 4) {
  $emailUnsafe = filter_var(safeStrFromExt('POST', 'email', 127), FILTER_SANITIZE_EMAIL);
  $isOldPwOk = processLoginData(
    dbConn:$dbConn,
    emailUnsafe:$emailUnsafe,
    passwordUnsafe:filter_var(safeStrFromExt('POST', 'password', 63), FILTER_SANITIZE_STRING), // generic string, max length 63
    setCookieSafe:0,
    doRedirect:FALSE
  );
  if ($isOldPwOk) {
    // check whether criterias are met (min-length = 4 characters)
    $newPw = filter_var(safeStrFromExt('POST', 'passwordNew', 63), FILTER_SANITIZE_STRING);
    if (strlen($newPw) < 4) {
      printErrorAndDie('Error','Neues Passwort muss mindestens 4 Zeichen lang sein... Nochmals versuchen? <a href="login.php" class="underline">zurück zur Login-Seite</a>');
    }
    $userid = mail2userid(dbConn:$dbConn, emailUnsafe:$emailUnsafe);
    if (!($userid > 0) ) { // email found in db
      printErrorAndDie('Error','Falsches Passwort oder Email... Nochmals versuchen? <a href="login.php" class="underline">zurück zur Login-Seite</a>');
      return FALSE; 
    } 

    $pwHash = password_hash($newPw, PASSWORD_DEFAULT);
    if (!($dbConn->query('UPDATE `user` SET `pwHash` = "'.$pwHash.'" WHERE `id` = "'.$userid.'"'))) {
      return error($dbConn, 104402);      
    }
  }
  printBeginOfPage(site:'login.php', title:'Passwort wurde geändert');
  echo '<p>Dein Passwort wurde erfolgreich geändert. Du kannst dich nun damit auf der <a href="login.php" class="underline">Loginseite</a> einloggen</p>';
} elseif ($doSafe === 5) {
  printBeginOfPage(site:'login.php', title:'Passwort vergessen');
  printLoginForm (reason:'forgot', formDo:6, submitText:'Passwort zurücksetzen');  
} elseif ($doSafe === 6) {
  printBeginOfPage(site:'login.php', title:'TODO: Link zum Zurücksetzen des Passworts verschickt');
  echo '<p>Funktion noch nicht implementiert...zurück zur <a href="login.php" class="underline">Loginseite</a></p>';
} else {
  printErrorAndDie('Error','unsupported do on login.php');
}
?>
</div></body></html>
