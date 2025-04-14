# using MicroPython v1.25.0-preview.540.g0b3ad98ea on 2025-04-12; Raspberry Pi Pico 2 W with RP2350
# REST API trial for display

from time import sleep
from machine import reset # type: ignore
import requests # type: ignore
import json

# my own files
import my_config
from my_functions import wlan_init, wlan_conn_check

# def getRestValues(): 
#     URL = "http://192.168.178.47/api/v1/report" # local network
#     try:
#         # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue 
#         response = urequests.get(url=URL)
#         if (response.status_code != 200):
#             print("invalid status code. Resetting in 20 seconds...")
#             sleep(20)             
#             reset() # NB: connection to whatever device is getting lost; complicates debugging
#         returnText = response.text
#         print("debugprint   Text:"+returnText)
#         response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
#         return(returnText)
#     except:
#         print("got an exception. Resetting in 20 seconds...") 
#         sleep(20) # add a bit of debug possibility        
#         reset() # NB: connection to whatever device is getting lost; complicates debugging
#         return # this return will never be executed

DBGCFG = my_config.get_debug_settings() # debug stuff
LOOP_WAIT_TIME = 5

device_config = my_config.get_device_config()
wlan = wlan_init(DBGCFG=DBGCFG)

loopCounter = 0

print("loopCounter:"+str(loopCounter))
wlan = wlan_conn_check(DBGCFG=DBGCFG, wlan=wlan) # check whether connection is still valid

response = requests.get("http://192.168.178.47/api/v1/report")
# response = requests.get("https://widmedia.ch/") # works
print('Response code: ', response.status_code)
print('Response encoding: ', response.encoding)
# hangs print('Response content:', response.content.decode())
print('Response content:', response.content.decode())
    
# response_content = response.json()
# print('Response content:', json.loads(response_content))

loopCounter = loopCounter + 1
sleep(LOOP_WAIT_TIME)
