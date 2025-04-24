# MBUS reader 

## input voltage conversion / full PCB
* Exhaustive project including an ESP: https://github.com/dev-lab/esp-iot-mbus
* replaced by TI chip: ~~(simple) mbus master circuit to transfer voltage: https://github.com/emard/mbus-circuit~~
* click interface board: https://www.mikroe.com/m-bus-slave-click, consists mainly of the TI chip https://download.mikroe.com/documents/datasheets/tss721a_datasheet.pdf

   * TI datasheet contains circuits including mcu power supply

* full solution with PCB included: https://roarfred.github.io/AmsToMqttBridge/Electrical/HAN_ESP_TSS721/. Parts cost about CHF20.- (temp sensor 5.80 can be removed). Github: https://github.com/roarfred/AmsToMqttBridge

Part list (2 are outdated): http://www.digikey.ch/short/jj1vhv

some software project relying on the HW above: https://github.com/aviborg/esp-smart-meter
