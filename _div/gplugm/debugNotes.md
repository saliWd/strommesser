# Debug steps

## gplug M config
1. supply with USB-C, not connected to E450
1. firmware update to 14.5.0 build date 3.3.2025, previously same version but different build date
   * download it from http://ota.gplug.ch/Tasmota/gPlugM/firmware.bin
1. change polarity on RJ12 connector: switch is now on the left side, close to RJ12 plug

## MBus measurements
1. measured on MPLUG SW. In lower row, the middle pin is switching from 16V to 28V (about 1sec low, 1 sec high), measured versus USB-C plug casing. No voltage on other 5 pins


## gplug behaviour
1. supplied only on RJ12. It does connect to the WLAN. Video of startup behaviour: LED starts blinking on startup, 5 times red-green-blue, afterwards LED is off


### logging outputs
logging output with sensor53 d1 and log level4: see file logFile_sensor53.txt
logging with script deactivated, serial output: see file logFile_serial.txt

