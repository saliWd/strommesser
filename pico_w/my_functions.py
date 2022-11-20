from time import sleep
from machine import Timer # type: ignore
from hashlib import sha256
from binascii import hexlify
from random import randint

import my_config

def debug_wdtFeed(wdt, DBGCFG:dict):
    if DBGCFG["wdt_dis"]:
        return
    wdt.feed()

def debug_print(DBGCFG:dict, text:str):
    if(DBGCFG["print"]):
        print(text)
    return # otherwise just return

def debug_sleep(wdt, DBGCFG:dict, time:int):
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    if(DBGCFG["sleep"]): # minimize wait times by sleeping only one second instead of the normal amount
        sleep(1)
        return
    remainingTime = time
    while remainingTime > 0:
        debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
        sleep(min(7,remainingTime)) # wdt is set to 8 secs
        remainingTime = remainingTime - 7
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    return

def get_wlan_ok(DBGCFG:dict, wlan):
    if(DBGCFG["wlan_sim"]):
        return(True)
    return(wlan.isconnected())

def wlan_connect(wdt, DBGCFG:dict, wlan, led_onboard, meas:bool):
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    wlan_ok_flag = get_wlan_ok(DBGCFG=DBGCFG, wlan=wlan)        
    if(wlan_ok_flag):
        return() # nothing to do
    else: # wlan is not ok
        for i in range(10): # set the time out
            debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
            config_wlan = my_config.get_wlan_config() # stored in external file
            wlan.connect(config_wlan['ssid'], config_wlan['pw'])
            sleep(3)
            wlan_ok_flag = get_wlan_ok(DBGCFG=DBGCFG, wlan=wlan)
            print("WLAN connected? "+str(wlan_ok_flag)+", loop var: "+str(i)) # debug output
            if (wlan_ok_flag):
                if(meas): # pimoroni does not have the led_onboard
                    led_onboard.toggle()
                debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
                return 
        # timeout, did not manage to get a working WLAN
        sleep(10) # sleep. This will trigger a wdt (and do a reboot). NB: connection to whatever device is getting lost. complicates debugging


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
