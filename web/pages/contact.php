<?php
 require_once('verbrauch/functions.php');
/* 
<input type="text"  name="contactForm_name" value="" placeholder="Vorname Nachname" spellcheck="false" /><br>
<input type="email" name="contactForm_email" id="contactForm_email" value="" placeholder="" spellcheck="false" /><br>
<input type="radio" name="contactForm_radio" value="leistungsMesser_E350">
<input type="radio" name="contactForm_radio" value="leistungsMesser_anders">
<input type="radio" name="contactForm_radio" value="leistungsMesser_unbekannt">
<textarea name="contactForm_div" rows="5" placeholder="" ></textarea>
<input type="checkbox" name="contactForm_process" value="1" >
*/
printBeginOfPage_v2(site:'contact.php', title:'Kontaktformular');
// $okOrNot = ($valid === count($userids_to_check)) ? 'ok' : '<span class="text-xl text-red-600">nicht ok</span>';
$name = safeStrFromExt(source:'POST', varName:'contactForm_name', length:63);
$email = safeStrFromExt(source:'POST', varName:'contactForm_email', length:63);
$type = safeIntFromExt(source:'POST', varName:'contactForm_radio', length:1);
$div = safeStrFromExt(source:'POST', varName:'contactForm_div', length:1023);
$process = safeIntFromExt(source:'POST', varName:'contactForm_process', length:1);

$okOrNot = 'Wartungsarbeiten (Stand 2023-06-16)...';
$output = 'Name: '.$name.', Email:'.$email.', Leistungsmesser: '.$type.', Weitere Infos: '.$div.', Datenverarbeitung: '.$process.'<br>';
echo '
<div class="text-left block p-6 bg-white border border-gray-200 rounded-lg shadow hover:bg-gray-100">
  <h3 class="mb-2 text-xl font-bold tracking-tight text-gray-900">'.$okOrNot.'</h3>
  <p class="font-normal text-gray-700">'.$output.'</p>
</div>
</div></body></html>';
?>