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
      1. have other 'columns' for graph generation, not just cons_tot/cons_diff but also cons_ht/cons_nt/ gen_tot/gen_diff/gen_ht/gen_nt
      1. have cost_chf graph (-> combine info from ht/nt and price info)
   4. ~~login.php~~
   5. ~~rx.php~~
   6. settings.php:
      1. set ht/nt price for both consumption and generation 
      1. which info to show on the pico device (generation, consumption, both?)
      1. which info to use on the graphs (generation, consumption, cost, combination (goes up with cons, goes down with gen))
      1. change email
   7. statistic.php
      1. explain text same as on start page, same layout etc.
3. pico devices
   1. ~~stability since 05.23: good since installing the try-except block on the devices, recovers from power outage.~~
   1. change to new micropython version
   1. print my own case for measurement
      * [ir head case][irHeadCase]
      * [pico case][picoCase]
   1. disp
      1. [case?][displayCase]
4. div


Next: 1.1.5? / 


[displayCase]: https://www.thingiverse.com/thing:4767008
[irHeadCase]: https://www.thingiverse.com/thing:3378332
[picoCase]: [https://www.thingiverse.com/thing:4895274]
