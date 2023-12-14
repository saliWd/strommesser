<?php
require_once('../verbrauch/functions.php');
printBeginOfPage_v2(site:'contact.php', title:'Kontaktformular');
$SPAM_SCHUTZ_TRUTH = 28;

$name = safeStrFromExt(source:'POST', varName:'contactForm_name', length:63);
$email = safeStrFromExt(source:'POST', varName:'contactForm_email', length:63);
$schutz = safeIntFromExt(source:'POST', varName:'contactForm_schutz', length:3);
$type = safeIntFromExt(source:'POST', varName:'contactForm_radio', length:1); // 1: E350, 2: anders, 3: unbekannt
$div = safeStrFromExt(source:'POST', varName:'contactForm_div', length:1023);
$process = safeIntFromExt(source:'POST', varName:'contactForm_process', length:1);

$okOrNot = '';
$procErr = FALSE;
$procErrDet = ''; // error message
$output = ''; // success message

if ($process !== 1) {
  $procErr = TRUE;
  $procErrDet .= 'Du musst der Datenverarbeitung zustimmen...<br>';
}
if ($schutz !== $SPAM_SCHUTZ_TRUTH) {
  $procErr = TRUE;
  $procErrDet .= 'Spamschutzrechnung ist nicht korrekt...<br>';
}
if (($type < 1) or ($type > 3)) {
  $procErr = TRUE;
  $procErrDet .= 'Modell Leistungsmesser nicht angegeben...<br>';
} else {
  if ($type === 1) {$type = 'E350';}
  if ($type === 2) {$type = 'anderes Modell';}
  if ($type === 3) {$type = 'unbekannt';}
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $procErr = TRUE;
  $procErrDet .= 'Emailadresse scheint ungültig zu sein...<br>';
}

if ($procErr) {
  $okOrNot = 'Fehler';
  $output .= '<div class="text-red-600">'.$procErrDet.'</div>Es wurde keine Email verschickt.<br><br>Bitte Kontaktformular nochmals ausfüllen: <a href="https://strommesser.ch/#post-194" class="underline">zurück</a><br><br>';
} else {
  $mailBody  = 'Name: '.$name."\n";
  $mailBody .= 'Email:'.$email."\n";
  $mailBody .= 'Leistungsmesser: '.$type."\n";
  $mailBody .= 'Weitere Infos: '.$div."\n";
  $mailBody .= 'Datenverarbeitung: '.$process."\n";

  $mailOk = mail(
    to:'messer@strommesser.ch;',
    subject:'Strommesser Kontaktanfrage',
    message:$mailBody
  );
  if ($mailOk) {
    $okOrNot = 'Kontaktdaten wurden verschickt';
    $output .= 'Email wurde verschickt (du erhältst wegen Spamschutz keine Kopie). Ich werde mich aber in Kürze bei dir melden...<br>Folgende Angaben wurden gesendet:<br>';
  } else {
    $okOrNot = 'Fehler beim Mailversand';
    $output .= 'Das Kontaktformular wurde korrekt ausgefüllt aber Email konnte nicht verschickt werden...<br>Nochmals versuchen? <br><a href="https://strommesser.ch/#post-194" class="underline">zurück</a>';
  }
}

$output .= '<div class="font-bold">Daten Kontaktformular</div>Name: '.$name.', Email:'.$email.', Leistungsmesser: '.$type.', Weitere Infos: '.$div.', Datenverarbeitung: '.$process.'<br>';
echo '
<div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">'.$okOrNot.'</h3>
  <p class="font-normal text-gray-700">'.$output.'</p>
  <p>&nbsp;</p>
  <p class="font-normal text-gray-700"><a href="https://strommesser.ch" class="underline">zurück zur Startseite</a></p>
</div>
</div></body></html>';
?>