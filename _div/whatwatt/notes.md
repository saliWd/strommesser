# MQTT setup
## WhatWatt device
URL: mqtt://192.168.178.44
Username: a
Password: b
Client ID: austr10
Publish Topic: energy/whatwatt/go
Payload:
{
	"meter_id":"${meter.id}",
	"timestamp":"${timestamp}",
	"P_In": ${1_7_0},
	"P_Out": ${2_7_0},
	"E_In": ${1_8_0},
	"E_In_T1": ${1_8_1},
	"E_In_T2": ${1_8_2},
	"E_Out": ${2_8_0}
}

## Raspi setup
(from https://randomnerdtutorials.com/how-to-install-mosquitto-broker-on-raspberry-pi/)
* sudo apt install -y mosquitto mosquitto-clients
* sudo systemctl enable mosquitto.service
* sudo nano /etc/mosquitto/mosquitto.conf
   * Move to the end of the file using the arrow keys and paste the following two lines:
   listener 1883
   allow_anonymous true
* sudo systemctl restart mosquitto

* mosquitto_sub -d -t energy/whatwatt/go

# REST API
## Polling method
Calling http://192.168.178.47/api/v1/report gets me a JSON response:

{"report":{"id":4524,"interval":5.238,"date_time":"2025-04-14T21:53:10Z","instantaneous_power":{"active":{"positive":{"total":0.001},"negative":{"total":0}}},"energy":{"active":{"positive":{"total":163.26,"t1":127.896,"t2":35.355},"negative":{"total":506.341,"t1":92.135,"t2":414.198}},"reactive":{"imported":{"inductive":{"total":147.264},"capacitive":{"total":10.076}},"exported":{"inductive":{"total":65.067},"capacitive":{"total":99.656}}}},"conv_factor":1},"meter":{"status":"OK","interface":"MBUS","protocol":"DLMS","id":"72913313","vendor":"Landis+Gyr","prefix":"LGZ"},"system":{"id":"ECC9FF5C80B0","date_time":"2025-04-14T20:53:12Z","boot_id":"3D5B5E1A","time_since_boot":20815}}

## Streaming method
Calling http://192.168.178.47/api/v1/live gets me continous live values:

event: live
data: {"P_In":0.007,"P_Out":0,"E_In":163.261,"E_In_T1":127.897,"E_In_T2":35.355,"E_Out":506.342,"E_Out_T1":92.136,"E_Out_T2":414.198,"Date":"2025-04-14","Time":"22:01:05","Uptime":5.91}

event: live
data: {"P_In":0,"P_Out":0,"E_In":163.261,"E_In_T1":127.897,"E_In_T2":35.355,"E_Out":506.342,"E_Out_T1":92.136,"E_Out_T2":414.198,"Date":"2025-04-14","Time":"22:01:10","Uptime":5.91}

event: live
data: {"P_In":0.013,"P_Out":0,"E_In":163.261,"E_In_T1":127.897,"E_In_T2":35.355,"E_Out":506.342,"E_Out_T1":92.136,"E_Out_T2":414.198,"Date":"2025-04-14","Time":"22:01:15","Uptime":5.91}

event: live
data: {"P_In":0.005,"P_Out":0,"E_In":163.261,"E_In_T1":127.897,"E_In_T2":35.355,"E_Out":506.342,"E_Out_T1":92.136,"E_Out_T2":414.198,"Date":"2025-04-14","Time":"22:01:20","Uptime":5.91}

