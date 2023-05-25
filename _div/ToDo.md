# StromMesser TODOs

1. StromMesser wordpress
   1. ~~move to /wp is ok (editor is behaving strange though)~~
   1. change back to Pique (better looking than lodestar). Do not use jetpack
   1. copy the content   
      * cta button on main page
      * need pages (short ones) as panel
         1. ~~Was ist es?~~ -> own panel
         1. Was brauchst du?
         1. Demo-Account (cta-2)
         1. Interesse (cta)
         1. Login (add form on it)
         1. Kontakt (not yet)
      * unclear how to integrate those two:
         * Abo
         * Geräte -> long page, more info.
   1. fill with more content: explanation?
   1. login form on WP / 'bigger' link
   

2. StromMesser/verbrauch
   1. general
      * design: navbar slightly different design?
   2. getRaw.php
   3. index.php
   4. login.php
   5. rx.php
   6. settings.php:
      * change email
      * csv-Export: delete exported data from dB
   7. statistic.php
3. pico devices
   1. stability since 10.01.23: mixed. Lots of good streaks (several days ok). issue 08.03.2023: several power cycles needed. 9.3.23 again
      * checking on WLAN side: if status is not 200, do a reboot? With a timeout? 
      * check with WLAN disconnection
      * could also be an issue on usb power supply?
      * does not (always) recover from issue on server side. Need more stable reset mechanism
   1. print my own case for measurement
      * [ir head case][irHeadCase]
      * [pico case][picoCase]
   1. disp
      1. [case?][displayCase]
4. div


Next: 3.2 / 1.2


[displayCase]: https://www.thingiverse.com/thing:4767008
[irHeadCase]: https://www.thingiverse.com/thing:3378332
[picoCase]: [https://www.thingiverse.com/thing:4895274]
