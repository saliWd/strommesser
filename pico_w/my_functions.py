## xx_version_placeholder_xx
from time import sleep
from hashlib import sha256
from binascii import hexlify
from random import randint
import urequests # type: ignore
from machine import reset # type: ignore

import my_config

def debug_print(DBGCFG:dict, text:str):
    if(DBGCFG["print"]):
        print(text)
    return # otherwise just return

def debug_sleep(DBGCFG:dict, time:int):
    if(DBGCFG["sleep"]): # minimize wait times by sleeping only one second instead of the normal amount
        sleep(1)
        return
    sleep(time)
    return

def get_wlan_ok(wlan):
    DBGCFG = my_config.get_debug_settings() # debug stuff
    if(DBGCFG["wlan_sim"]):
        return(True)
    return(wlan.isconnected())

def wlan_connect(wlan, led_onboard, meas:bool):
    wlan_ok_flag = get_wlan_ok(wlan=wlan)        
    if(wlan_ok_flag):
        return() # nothing to do
    else: # wlan is not ok
        for i in range(20): # set the time out
            config_wlan = my_config.get_wlan_config() # stored in external file
            wlan.connect(config_wlan['ssid'], config_wlan['pw'])
            sleep(8)
            wlan_ok_flag = get_wlan_ok(wlan=wlan)
            print("WLAN connected? "+str(wlan_ok_flag)+", loop var: "+str(i)) # debug output
            if (wlan_ok_flag):
                if(meas): # pimoroni does not have the led_onboard
                    led_onboard.toggle()
                return 
        # timeout, did not manage to get a working WLAN        
        reset() # NB: connection to whatever device is getting lost; complicates debugging


def urlencode(dictionary:dict):
    urlenc = ""
    for key, val in dictionary.items():
        urlenc += "%s=%s&" %(key,val)
    urlenc = urlenc[:-1] # gets me something like 'val0=23&val1=bla space'
    return(urlenc)

def transmit_message(DBGCFG:dict, URL:str, message:dict):
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    try:
        urlenc = urlencode(message)
        # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue 
        response = urequests.post(URL, data=urlenc, headers=HEADERS)
        if (response.status_code != 200):
            print("invalid status code. Resetting in 20 seconds...")
            sleep(20)             
            reset() # NB: connection to whatever device is getting lost; complicates debugging
        returnText = response.text
        debug_print(DBGCFG=DBGCFG, text="Text:"+returnText)
        response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
        return(returnText)
    except:
        sleep(20) # add a bit of debug possibility        
        reset() # NB: connection to whatever device is getting lost; complicates debugging
        return # this return will never be executed


def get_randNum_hash(device_config):
    rand_num = randint(1, 10000)
    myhash = sha256(str(rand_num)+device_config['post_key'])
    hashString = hexlify(myhash.digest())
    returnVal = dict([
        ('randNum', rand_num),
        ('hash', hashString.decode())
    ])
    return(returnVal)
