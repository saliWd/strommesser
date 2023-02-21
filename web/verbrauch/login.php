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

function processLoginData(object $dbConn, string $emailUnsafe, string $passwordUnsafe, int $setCookieSafe): bool {
  if (!(filter_var($emailUnsafe, FILTER_VALIDATE_EMAIL))) { // have a valid email
      printErrorAndDie('Error','Email ungültig');      
  }
  $userid = mail2userid(dbConn:$dbConn, emailUnsafe:$emailUnsafe);
  if (!($userid > 0) ) { // email found in db
    printErrorAndDie('Error','Falsches Passwort oder Email... Nochmals versuchen? <a href="login.php">zurück zur Login-Seite</a>');
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
  redirectRelative('index.php');
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

/*
function updateUser (object $dbConn, int $userid, bool $forgotPw): bool {  
  if (!(isNotTestUser($dbConn, $userid))) {
    return false;
  }
  if (!($result = $dbConn->query('SELECT * FROM `user` WHERE `id` = "'.$userid.'"'))) {
    return error($dbConn, 104404);
  }
  
  $row = $result->fetch_assoc(); // guaranteed to get only one row      
  $passwordUnsafe = safeStrFromExt('POST','password', 63);
  if (!(($forgotPw) or (password_verify($passwordUnsafe, $row['pwHash'])))) {        
    return error($dbConn, 104403);
  }
    
  $passwordUnsafe = filter_var(safeStrFromExt('POST','passwordNew', 63), FILTER_SANITIZE_STRING);
  if (strlen($passwordUnsafe) <= 3) {
    return error($dbConn, 104400);
  }
  $pwHash = password_hash($passwordUnsafe, PASSWORD_DEFAULT);
  
  // TODO: quiet ugly statements...
  $emailOk = false;
  if (!$forgotPw) {
    $emailUnsafe = filter_var(safeStrFromExt('POST','email', 127), FILTER_SANITIZE_EMAIL);
    // newEmail must not exist in the db (exclude current user itself)
    if (filter_var($emailUnsafe, FILTER_VALIDATE_EMAIL)) { // have a valid email 
      // check whether email already exists
      $emailSqlSafe = mysqli_real_escape_string($dbConn, $emailUnsafe);
      if (strcasecmp($emailSqlSafe, $row['email'])  != 0) { // 0 means they are equal
        if ($result = $dbConn->query('SELECT `verified` FROM `user` WHERE `email` LIKE "'.$emailSqlSafe.'" LIMIT 1')) {
          if ($result->num_rows == 0) {
            $emailOk = true; 
          }
        }
      } else { $emailOk = true; }; // no need to check again if the email did not change
    }
  }
    
  if ($emailOk) {
    if (!($dbConn->query('UPDATE `user` SET `pwHash` = "'.$pwHash.'", `email` = "'.$emailSqlSafe.'" WHERE `id` = "'.$userid.'"'))) {
      return error($dbConn, 104401);
    }
    return true;
  } else { 
    if (!$forgotPw) { 
      return error($dbConn, 104405);
    }
    if (!($dbConn->query('UPDATE `user` SET `pwHash` = "'.$pwHash.'" WHERE `id` = "'.$userid.'"'))) {
      return error($dbConn, 104402);      
    }
    return true;
  } // emailOK-else  
}




function newUserLoginAndLinks (object $dbConn, int $newUserid, string $pw) : bool {       
  // password_hash("messerPW", PASSWORD_DEFAULT) returns '$2y$10$zd4qDdeg59iqGV7GrviV9eLw.B9OD/JVTIul8rr1IPp9oWJd4AZAy';
  $pwHash = password_hash($pw, PASSWORD_DEFAULT); // $pw is potentially unsafe. Shouldn't be an issue as I store the hash
  
  // NB: set a cookie for some random big number. Not the password itself and not the pwHash!
  // NB: will use this number on every cookie for this user, to login on several devices. One cannot guess other users random number                  
  $hexStr64 = bin2hex(random_bytes(32)); // some random value, used for cookie     
  if (!($dbConn->query('UPDATE `user` SET `pwHash` = "'.$pwHash.'", `randCookie` = "'.$hexStr64.'" WHERE `id` = "'.$newUserid.'"'))) { 
    return FALSE;
  }
  return TRUE;
} 
*/

$doSafe = safeIntFromExt('GET', 'do', 1); // this is an integer (range 1 to 9) or non-existing
// do = 0: entry point
// do = 1: process login form
// do = 2: logout
// do = 3: present changePW form
// do = 4: execute the changePW
// do = 5: present the forgotPW form
// do = 6: execute the forgotPW

if ($doSafe === 0) {
  // check cookie
  $useridCookieSafe = safeIntFromExt('COOKIE', 'userIdCookie', 11);
  $randCookieSafe   = safeHexFromExt('COOKIE', 'randCookie', 64); 
  if (($useridCookieSafe > 0) and (verifyCredentials(dbConn:$dbConn, authMethodPw:FALSE, userid:$useridCookieSafe, randCookieInput:$randCookieSafe))){
    redirectRelative('index.php'); // always going back to the main page after login   
    die(); // will not be executed
  } // no cookie present and no userid. print the login form

  printBeginOfPage(site:'login.php', title:'Log in');
  echo '
  <form action="login.php?do=1" method="post" id="loginForm">
    <div class="grid grid-cols-2 gap-4 justify-items-start mt-8">
      <div class="justify-self-end">Email:</div>
      <div><input class="input-text" name="email" type="email" maxlength="127" value="" required></div>
      <div class="justify-self-end">Passwort:</div>
      <div><input class="input-text" name="password" type="password" maxlength="63" value="" required></div>
      <div class="justify-self-end"><input class="w-10" type="checkbox" name="setCookie" value="1" checked></div>
      <div class="text-sm">auf diesem Gerät speichern</div>
      <div class="justify-self-center col-span-2"><input id="loginFormSubmit" class="mt-8 input-text" name="submit" type="submit" value="log in"></div>      
      <div class="mt-16 justify-self-center"><a href="login.php?do=3" class="btn">Passwort ändern</a></div>
      <div class="mt-16 justify-self-center"><a href="login.php?do=5" class="btn">Passwort vergessen</a></div>
    </div>
  </form>
  ';  
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
  echo '
  <form action="login.php?do=4" method="post" id="loginForm">
    <div class="grid grid-cols-2 gap-4 justify-items-start mt-8">
      <div class="justify-self-end">Email:</div>
      <div><input class="input-text" name="email" type="email" maxlength="127" value="" required></div>
      <div class="justify-self-end">altes Passwort:</div>
      <div><input class="input-text" name="password" type="password" maxlength="63" value="" required></div>
      <div class="justify-self-end">neues Passwort:</div>
      <div><input class="input-text" name="passwordNew" type="password" maxlength="63" value="" required></div>
      <div class="justify-self-center col-span-2"><input id="loginFormSubmit" class="mt-8 input-text" name="submit" type="submit" value="Passwort ändern"></div>      
    </div>
  </form>
  ';  
} elseif ($doSafe === 4) {
  printBeginOfPage(site:'login.php', title:'TODO: Passwort wurde geändert');
  echo '<p>Funktion noch nicht implementiert...zurück zur <a href="login.php" class="underline">Loginseite</a></p>';
} elseif ($doSafe === 5) {
  printBeginOfPage(site:'login.php', title:'TODO: Passwort vergessen');
  echo '<p>Funktion noch nicht implementiert...zurück zur <a href="login.php" class="underline">Loginseite</a></p>';
} elseif ($doSafe === 6) {
  printBeginOfPage(site:'login.php', title:'TODO: Link zum Zurücksetzen des Passworts verschickt');
  echo '<p>Funktion noch nicht implementiert...zurück zur <a href="login.php" class="underline">Loginseite</a></p>';
} else {
  printErrorAndDie('Error','unsupported do on login.php');
}
?>
</div></body></html>
