<?php declare(strict_types=1); 
require_once('functions.php');
session_start(); // this code must precede any HTML output
$dbConn = get_dbConn(); // do not use initialize here

$_SESSION['userid'] = 0; // the most important one, make sure it's really 0
setcookie(name:'userIdCookie', value:'0', expires_or_options:(time() - 42000), path:'/verbrauch/', domain:'strommesser.ch', secure:TRUE, httponly:TRUE); // some big enough value in the past to make sure things like summer time changes do not affect it

printBeginOfPage_v2(site:'login.php', title:'Log out');
echo '<p>log out ok, zurück zur <a href="../index.php" class="underline">Startseite</a></p>';
?>
</div></body></html>
