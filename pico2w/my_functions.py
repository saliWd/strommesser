from time import sleep
from hashlib import sha256
from binascii import hexlify
from random import randint
import requests_1 as request
from machine import reset # type: ignore
import network # type: ignore (this is a pylance ignore warning directive)
import gc

import my_config

def debug_print(DEBUG_CFG:dict, text:str):
    if(DEBUG_CFG['print']):
        print(text)
    return # otherwise just return

def debug_sleep(DEBUG_CFG:dict, time:int):
    if(DEBUG_CFG['sleep'] == 'short'): # minimize wait times by sleeping only one second instead of the normal amount
        time = 1
    sleep(time)
    return

# is called once before while loop
def wlan_init(DEBUG_CFG:dict):
    if(DEBUG_CFG['wlan'] == 'simulated'):
        print('WLAN connection is simulated...')
        return() # no meaningful return value

    config_wlan = my_config.get_wlan_config() # stored in external file            
    wlanStatus = 0
    waitCounter = 0
    while wlanStatus != 3:
        print('waiting for connection...WLAN Status: '+str(wlanStatus)+'. Counter: '+str(waitCounter))
        wlan = network.WLAN(network.STA_IF)
        wlan.active(True) # activate it. NB: disabling does not work correctly
        sleep(2)
        wlan.connect(config_wlan['ssid'], config_wlan['pw'])
        wlanStatus = wlan.status()
        # need to restart all, otherwise the status is always constant
        if wlanStatus != 3:
            wlan.active(False)
            del wlan
            gc.collect()
        
        waitCounter += 1
        sleep(2)

    wlanIfconfig = wlan.ifconfig()
    print('connected. IP: ' + wlanIfconfig[0])
    return(wlan)

# is called in every while loop
def wlan_conn_check(DEBUG_CFG:dict, wlan):
    if (DEBUG_CFG['wlan'] == 'simulated'):
        return() # no meaningful return value
    if(wlan.isconnected()):
        return(wlan) # nothing to do
    else:
        wlan.active(False)
        del wlan
        gc.collect() # garbage collection
        wlanNew = wlan_init(DEBUG_CFG=DEBUG_CFG) # call the init
        return(wlanNew)


def urlencode(dictionary:dict):
    urlenc = ""
    for key, val in dictionary.items():
        urlenc += "%s=%s&" %(key,val)
    urlenc = urlenc[:-1] # gets me something like 'val0=23&val1=bla space'
    return(urlenc)

def transmit_message(DEBUG_CFG:dict, URL:str, message:dict):
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    try:
        urlenc = urlencode(message)
        # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue 
        response = request.post(URL, data=urlenc, headers=HEADERS)
        if (response.status_code != 200):
            print("invalid status code. Resetting in 20 seconds...")
            sleep(20)             
            reset() # NB: connection to whatever device is getting lost; complicates debugging
        returnText = response.text
        debug_print(DEBUG_CFG=DEBUG_CFG, text="Text:"+returnText)
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
