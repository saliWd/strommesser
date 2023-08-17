# StromMesser TODOs

1. StromMesser wordpress
   1. copy the content from WP to static page
      * cta button on main page: Kontakt + Demo-Account
      * parts / panels
         0. main page: change strommesser image (currently white background)
         1. ~~Auswertungen~~
         2. ~~Was braucht es dazu?~~
         3. Demo-Account (cta-2)
         4. Kontakt (cta) -> source code fragment, solve blueish border
         5. Login (add form on it)
      * ~~Two additional pages:~~
         * ~~Abo~~
         * ~~Geräte -> long page, more info.~~
   1. fill with more content: explanation?
2. StromMesser/verbrauch
   1. general
      * design: navbar slightly different design, darker colour?
   2. ~~getRaw.php~~
   3. index.php
      1. make it configurable, which graphs to show (in settings)
      1. have cost_chf graph (-> combine info from ht/nt and price info)
   4. ~~login.php~~
   5. ~~rx.php~~
   6. settings.php:
      1. set ht/nt price for both consumption and generation 
      1. different ranges for consumption and generation
      1. which graphs to show on index
      1. change email
   7. statistic.php
      1. add table layout with numbers additional to graphs
3. pico devices
   1. ~~stability since 05.23: good since installing the try-except block on the devices, recovers from power outage.~~
   1. print my own case for measurement
      * [ir head case][irHeadCase]
      * [pico case][picoCase]
   1. disp
      1. different color coding for the LED [algorithm][hsvToRgb]
      1. [case?][displayCase]
4. div


Next: / 


[displayCase]: https://www.thingiverse.com/thing:4767008
[irHeadCase]: https://www.thingiverse.com/thing:3378332
[picoCase]: [https://www.thingiverse.com/thing:4895274]
[hsvToRgb]: [https://stackoverflow.com/questions/3018313/algorithm-to-convert-rgb-to-hsv-and-hsv-to-rgb-in-range-0-255-for-both]
