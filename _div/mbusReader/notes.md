# MBUS reader 

## input voltage conversion / full PCB
* Exhaustive project including an ESP: https://github.com/dev-lab/esp-iot-mbus
* replaced by TI chip: ~~(simple) mbus master circuit to transfer voltage: https://github.com/emard/mbus-circuit~~
* click interface board: https://www.mikroe.com/m-bus-slave-click, consists mainly of the TI chip https://download.mikroe.com/documents/datasheets/tss721a_datasheet.pdf

   * TI datasheet contains circuits including mcu power supply

* full solution with PCB included: https://roarfred.github.io/AmsToMqttBridge/Electrical/HAN_ESP_TSS721/. Parts cost about CHF20.- (temp sensor 5.80 can be removed). Github: https://github.com/roarfred/AmsToMqttBridge

Part list (2 are outdated): http://www.digikey.ch/short/jj1vhv

### Part list (updated)
1. ok    C1/C4/C5 	   220uF / 16V 	UVR1C221MED1TA 	Electrolytic Capacitor
1. NOK   C2/C3/C6       100nF / 63V 	R82EC3100AA70J 	Metal Film Capasitor. Replacement: R82EC3100Z370J
1. ok    R1 	         22k 	         CF14JT22K0 	      0.25W resistor
1. ok    R2 	         470R 	         CF14JT470R 	      0.25W resistor
1. ok    R3/R4/R5/R6/R7 10k 	         CF14JT10K0 	      0.25W resistor
1. ok    R8/R9          220R 	         CF14JT220R 	      0.25W resistor
1. ok    R10 	         22k 	         CF14JT22K0 	      0.25W resistor
1. ok    R11 	         4k7 	         CF14JT4K70 	      0.25W resistor
1. NOK   U1 	         TSS721 	      TSS721AD 	      M-bus tranciever. Replacement: TSS721ADR (same package, seems pin compatible)
1. ok    U2 	         ESP12-E 	      ESP12 	         ESP8266 SMT MODULE
1. ok    U3 	         LM1117-3.3 	   LD1117AS33TR 	   3.3V Voltage regulator
1. DEL   U4 	         DS18B20 	      DS18B20 	         Maxim Temp Sensor. Don't need it
1. NOK   Q1 	         BSS84 	      BSS84PH6433XTMA1 	P-FET transistor. Replacement: BSS84PH6327XTSA2
1. NOK   J1 	         RJ45 	         54601-908WPLF 	   RJ45 port. Replacement: 1705951-1
1. ok    J2 	         uUSB 	         10118194-0001LF 	USB micro socket
1. NOK   J3 	         6-pin header 	4320-01074-0 	   6-pin female header. Replacement: TODO
1. ok    JP1/JP2 		                  XG8S-0241 	      Jumper header
1. ok    SW1/SW2 	      Switch 	      1825910-6 	      Tactile Button
1. ok    NONAME         2 x Jumper     STC02SYAN         Unless you have these lying in your drawer, you'll need two

Unclear: diff RJ45/RJ12? Replacement for 6pin header, too many options on digikey


some software project relying on the HW above: https://github.com/aviborg/esp-smart-meter
