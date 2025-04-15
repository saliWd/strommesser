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
URL = "https://strommesser.ch/json.php"     # works sometimes, sometimes getting EHOSTUNREACH, sometimes hangs
URL = "https://widmedia.ch" # works
# URL = "https://strommesser.ch/trial.json" # works

print('a: starting get request')
try:
    response = urequests.get(URL, timeout=9)
    print('b: get request done')

    print('c: printing status code')
    print('Response code: ', response.status_code)

    print('d: printing encoding')
    print('Response encoding: ', response.encoding)

    print('e: printing content')
    print('Response content:', response.content)
    response.close()
    del response
except:
    print('did get an exception')
    

# response_content = response.json()
# print('Response content:', json.loads(response_content))


del URL
del wlan, device_config, DBGCFG
gc.collect() # garbage collection
sleep(3)
print('done')
