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

LOOP_COUNT_MAX = 5 # program runs for this long
LOOP_SLEEP_SEC = 5


# get request is very unstable
def json_get_request(URL:str):
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(False)
        jdata = json.loads(response.content)
        response.close()
        get_interesting_values(jdata=jdata)
        return(True)
    except Exception as error:
        print("An exception occurred:", error)
        return(False)

def get_interesting_values(jdata):
    # print("Content:\n", jdata)
    print('time since boot:', jdata['system']['time_since_boot'])
    print('date time:', jdata['system']['date_time'])
    print('current power +:', jdata['report']['instantaneous_power']['active']['positive']['total'])
    print('current power -:', jdata['report']['instantaneous_power']['active']['negative']['total'])

    print('energy +:', jdata['report']['energy']['active']['positive']['total'])
    print('energy -:', jdata['report']['energy']['active']['negative']['total'])
    
    print('energy + T1:', jdata['report']['energy']['active']['positive']['t1'])
    print('energy + T2:', jdata['report']['energy']['active']['positive']['t2'])
    

loopCount = 0
while True:
    print('loop: ',loopCount)
    if not json_get_request(URL=URL):
        print('get request did not work')
    sleep(LOOP_SLEEP_SEC)
    loopCount += 1
    if loopCount > LOOP_COUNT_MAX:
        break
    
    



del URL
del wlan, device_config, DBGCFG
gc.collect() # garbage collection
sleep(1)
print('done')

