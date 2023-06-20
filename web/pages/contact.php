<?php
 require_once('../verbrauch/functions.php');
/* 
<input type="text"  name="contactForm_name" value="" placeholder="Vorname Nachname" spellcheck="false" /><br>
<input type="email" name="contactForm_email" id="contactForm_email" value="" placeholder="" spellcheck="false" /><br>
<input type="radio" name="contactForm_radio" value="1">
<input type="radio" name="contactForm_radio" value="2">
<input type="radio" name="contactForm_radio" value="3">
<textarea name="contactForm_div" rows="5" placeholder="" ></textarea>
<input type="checkbox" name="contactForm_process" value="1" >
*/
printBeginOfPage_v2(site:'contact.php', title:'Kontaktformular');
// $okOrNot = ($valid === count($userids_to_check)) ? 'ok' : '<span class="text-xl text-red-600">nicht ok</span>';
$name = safeStrFromExt(source:'POST', varName:'contactForm_name', length:63);
$email = safeStrFromExt(source:'POST', varName:'contactForm_email', length:63);
$type = safeIntFromExt(source:'POST', varName:'contactForm_radio', length:1); // 1: E350, 2: anders, 3: unbekannt
$div = safeStrFromExt(source:'POST', varName:'contactForm_div', length:1023);
$process = safeIntFromExt(source:'POST', varName:'contactForm_process', length:1);

$okOrNot = '';
$procErr = FALSE;
$procErrDet = ''; // error message
$output = ''; // sucess message

if ($process !== 1) {
  $procErr = TRUE;
  $procErrDet .= 'Du musst der Datenverarbeitung zustimmen...<br><br>';
}
if (($type < 1) or ($type > 3)) {
  $procErr = TRUE;
  $procErrDet .= 'Modell Leistungsmesser nicht angegeben...<br><br>';
} else {
  if ($type === 1) {$type = 'E350';}
  if ($type === 2) {$type = 'anderes Modell';}
  if ($type === 2) {$type = 'unbekannt';}
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $procErr = TRUE;
  $procErrDet .= 'Emailadresse scheint ungültig zu sein...<br><br>';  
}

if ($procErr) {
  $okOrNot = 'Fehler';
  $output .= $procErrDet.'Es wurde keine Email verschickt.<br><br><br>Bitte Kontaktformular nochmals ausfüllen: <a href="https://strommesser.ch/#post-194" class="underline">zurück</a><br><br>';
} else {
  $mailBody  = 'Name: '.$name."\n";
  $mailBody .= 'Email:'.$email."\n";
  $mailBody .= 'Leistungsmesser: '.$type."\n";
  $mailBody .= 'Weitere Infos: '.$div."\n";
  $mailBody .= 'Datenverarbeitung: '.$process."\n";

  $mailOk = mail(
    to:'messer@strommesser.ch;'.$email,
    subject:'Strommesser Kontaktanfrage',
    message:$mailBody    
  );
  if ($mailOk) {
    $okOrNot = 'Kontaktdaten wurden verschickt';
    $output .= 'Email wurde verschickt (du erhältst eine Kopie). Ich werde mich in Kürze bei dir melden...<br>Folgende Angaben wurden gesendet:<br>';
  } else {
    $okOrNot = 'Fehler beim Mailversand';
    $output .= 'Das Kontaktformular wurde korrekt ausgefüllt aber Email konnte nicht verschickt werden...<br>Nochmals versuchen? <br><a href="https://strommesser.ch/#post-194" class="underline">zurück</a>';
  }

}

$output .= 'Name: '.$name.', Email:'.$email.', Leistungsmesser: '.$type.', Weitere Infos: '.$div.', Datenverarbeitung: '.$process.'<br>';
echo '
<div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">'.$okOrNot.'</h3>
  <p class="font-normal text-gray-700">'.$output.'</p>
</div>
</div></body></html>';
?>