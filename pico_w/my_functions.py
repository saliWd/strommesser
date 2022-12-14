from time import sleep
from hashlib import sha256
from binascii import hexlify
from random import randint

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

def get_wlan_ok(DBGCFG:dict, wlan):
    if(DBGCFG["wlan_sim"]):
        return(True)
    return(wlan.isconnected())

def wlan_connect(DBGCFG:dict, wlan, led_onboard, meas:bool):
    wlan_ok_flag = get_wlan_ok(DBGCFG=DBGCFG, wlan=wlan)        
    if(wlan_ok_flag):
        return() # nothing to do
    else: # wlan is not ok
        for i in range(10): # set the time out
            config_wlan = my_config.get_wlan_config() # stored in external file
            wlan.connect(config_wlan['ssid'], config_wlan['pw'])
            sleep(3)
            wlan_ok_flag = get_wlan_ok(DBGCFG=DBGCFG, wlan=wlan)
            print("WLAN connected? "+str(wlan_ok_flag)+", loop var: "+str(i)) # debug output
            if (wlan_ok_flag):
                if(meas): # pimoroni does not have the led_onboard
                    led_onboard.toggle()
                return 
        # timeout, did not manage to get a working WLAN
        from machine import reset # type: ignore
        reset() # NB: connection to whatever device is getting lost; complicates debugging


def urlencode(dictionary:dict):
    urlenc = ""
    for key, val in dictionary.items():
        urlenc += "%s=%s&" %(key,val)
    urlenc = urlenc[:-1] # gets me something like 'val0=23&val1=bla space'
    return(urlenc)

def get_randNum_hash(device_config):
    rand_num = randint(1, 10000)
    myhash = sha256(str(rand_num)+device_config['post_key'])
    hashString = hexlify(myhash.digest())
    returnVal = dict([
        ('randNum', rand_num),
        ('hash', hashString.decode())
    ])
    return(returnVal)
