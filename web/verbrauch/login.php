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
// do = 3: process login form with demo account info

if ($doSafe === 0) {
  // check cookie
  $useridCookieSafe = safeIntFromExt('COOKIE', 'userIdCookie', 11);
  $randCookieSafe   = safeHexFromExt('COOKIE', 'randCookie', 64); 
  if (($useridCookieSafe > 0) and (verifyCredentials(dbConn:$dbConn, authMethodPw:FALSE, userid:$useridCookieSafe, randCookieInput:$randCookieSafe))){
    redirectRelative('index.php'); // always going back to the main page after login   
    die(); // will not be executed
  } // no cookie present and no userid. print the login form

  printBeginOfPage(enableReload:FALSE, timerange:'', site:'login.php', title:'Log in');
  echo '
  <form action="login.php?do=1" method="post" id="loginForm">
    <div class="grid grid-cols-2 gap-4 justify-items-start mt-8">
      <div class="justify-self-end">Email:</div>
      <div><input class="input-text" name="email" type="email" maxlength="127" value="" required></div>
      <div class="justify-self-end">Passwort:</div>
      <div><input class="input-text" name="password" type="password" maxlength="63" value="" required></div>
      <div class="justify-self-end"><input class="w-10" type="checkbox" name="setCookie" value="1" checked></div>
      <div class="text-sm">auf diesem Gerät speichern</div>
      <div class="justify-self-center col-span-2"><input id="loginFormSubmit" class="mt-8 input-text" name="create" type="submit" value="log in"></div>      
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
  printBeginOfPage(enableReload:FALSE, timerange:'', site:'login.php', title:'Log out');
  echo '<p>log out ok, zurück zur <a href="../index.php" class="underline">Startseite</a></p>';
} elseif ($doSafe === 3) {  
  processLoginData(
    dbConn:$dbConn,
    emailUnsafe:filter_var('messer@strommesser.ch', FILTER_SANITIZE_EMAIL), // demo account
    passwordUnsafe:filter_var('messerPW', FILTER_SANITIZE_STRING), // demo account
    setCookieSafe:safeIntFromExt('POST', 'setCookie', 1)
  ); // this redirects on success
} else {
  printErrorAndDie('Error','unsupported do on login.php');
}
?>
</div></body></html>
