# using MicroPython v1.25.0-preview.540.g0b3ad98ea on 2025-04-12; Raspberry Pi Pico 2 W with RP2350
# REST API trial for display

from time import sleep
import requests_1 as request # from https://github.com/shariltumin/bit-and-pieces/tree/main/web-client
# see also https://github.com/orgs/micropython/discussions/14105
import json
import gc

# my own files
import my_config
from my_functions import wlan_init

DBGCFG = my_config.get_debug_settings() # debug stuff

device_config = my_config.get_device_config()
wlan = wlan_init(DBGCFG=DBGCFG)

URL = "http://192.168.178.47/api/v1/report" 
# URL = "https://strommesser.ch/json_long.php"

# get request is very unstable
def json_get_request(URL:str):
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(False)
        jdata = json.loads(response.content)
        response.close()
        return(jdata)
    except Exception as error:
        print("An exception occurred:", error)
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
    print("Content:\n", content)
else:
    print('get request did not work')



del URL
del wlan, device_config, DBGCFG
gc.collect() # garbage collection
sleep(1)
print('done')

