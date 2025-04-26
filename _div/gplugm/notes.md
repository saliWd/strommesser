# Notes

## gplug M config
1. supply with USB-C, not connected to E450
1. firmware update to 14.5.0 build date 3.3.2025, previously same version but different build date
   * download it from http://ota.gplug.ch/Tasmota/gPlugM/firmware.bin
1. change polarity on RJ12 connector: switch is now on the left side, close to RJ12 plug

# REST API
JSON output available on:
* http://192.168.178.58/cm?cmnd=status0 gets me the info in statusSNS
* http://192.168.178.58/cm?cmnd=status%2010 gets me only the status of the sensor
