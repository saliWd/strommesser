# using MicroPython v1.25.0-preview.540.g0b3ad98ea on 2025-04-12; Raspberry Pi Pico 2 W with RP2350
# REST API trial for display

from time import sleep
import urequests # type: ignore
# import json
import gc

# my own files
import my_config
from my_functions import wlan_init, wlan_conn_check

DBGCFG = my_config.get_debug_settings() # debug stuff

device_config = my_config.get_device_config()
wlan = wlan_init(DBGCFG=DBGCFG)

# wlan = wlan_conn_check(DBGCFG=DBGCFG, wlan=wlan) # check whether connection is still valid

URL = "http://192.168.178.47/api/v1/report" # tbd
URL = "https://strommesser.ch/json.php"
# URL = "https://widmedia.ch" # works
# URL = "https://strommesser.ch/trial.json" # works

# get request is very unstable
def json_get_request(URL:str):
    try:
        response = urequests.get(URL, timeout=9)
        if (response.status_code != 200):
            return(False)
        text = response.content
        response.close()
        del response
        return(text)
    except:
        print('did get an exception')
        return(False)

trialCount = 0
MAX_TRIAL = 5

while trialCount < MAX_TRIAL:
    content = json_get_request(URL=URL)
    if content:
        break
    else:
        trialCount = trialCount + 1
        print('get trial: ', trialCount)
        sleep(1)

if trialCount < MAX_TRIAL:
    print('Content: ', content)
else:
    print('get request did not work')



del URL
del wlan, device_config, DBGCFG
gc.collect() # garbage collection
sleep(3)
print('done')
