# using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# does not work: Micropython version Raspberry Pico 2 W with Pimoroni libraries 0.0.11
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

import requests_1 as request # from https://github.com/shariltumin/bit-and-pieces/tree/main/web-client, see also https://github.com/orgs/micropython/discussions/14105
import json
import gc
from time import sleep
from hashlib import sha256
from binascii import hexlify
from random import randint
import network # type: ignore (this is a pylance ignore warning directive)

# my own files
import my_config

# start with h = variable, s = 0.5, v = 0.5, a = LedBrightness/255
def hsva_to_rgb(h:float, s:float, v:float, a:float) -> tuple: # inputs: values from 0.0 to 1.0. Outputs are integers, range 0 to 255
    if s:
        if h == 1.0: h = 0.0
        i = int(h*6.0); f = h*6.0 - i
        
        w = int(255*a*( v * (1.0 - s) ))
        q = int(255*a*( v * (1.0 - s * f) ))
        t = int(255*a*( v * (1.0 - s * (1.0 - f)) ))
        v = int(255*a*v)
        
        if i==0: return (v, t, w)
        if i==1: return (q, v, w)
        if i==2: return (w, v, t)
        if i==3: return (w, q, v)
        if i==4: return (t, w, v)
        if i==5: return (v, w, q)
    else: v = int(255*v); return (v, v, v)

def val_to_rgb(val:int, minValCons:int, maxValGen:int, led_brightness:int)-> list: # goes from red to blue
    # value has a range from -minValCons to +maxValGen. minVal and maxVal are both positive numbers but minVal may be treated as negative
    if val < 0: val = val * 2 # get a higher color 'resolution' for negative values
    val = val + 2*minValCons # bring it to the range 0..(min+max)
    h = float(val) / float(1.4*(2*minValCons+maxValGen)) # h value makes a 'circle'. This means 0 degree is the same as 360°. -> Need to limit it (but not to 180°, just less than 360)
    a = float(led_brightness) / float(255)
    return list(hsva_to_rgb(h, 1.0, 1.0, a))

def right_align(value4digits:int):
    if value4digits < 10:
        return "   "
    if value4digits < 100:
        return "  "
    if value4digits < 1000:
        return " "
    return ""

def make_bold(display, text:str, x:int, y:int): # making it 'bold' by shifting it 1px right (not very nice hack)
    display.text(text, x, y, scale=1.1)
    display.text(text, x+1, y, scale=1.1)

def getDispYrange(values:list) -> list:
    """returns the range of the given values. extends the range to at least -50 to +50 if the min/max are smaller. returns 2 positive values"""
    minimum = abs(min(min(values),-50))
    maximum = max(max(values),50)
    return [minimum,maximum,(minimum+maximum)]

# get request might be unstable (depends on internet connection)
def json_get_request(DEBUG_CFG:dict) -> dict:
    URL = 'http://192.168.178.47/api/v1/report' # on the local net
    if DEBUG_CFG['json_data'] == 'web': # can be 'local_net'|'web'|'file'
        URL = "https://strommesser.ch/json_long.php"
    elif DEBUG_CFG['json_data'] == 'file':         
        return(get_interesting_values(DEBUG_CFG=DEBUG_CFG, jdata=my_config.debug_jdata()))
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(dict([('valid',False)]))
        jdata = json.loads(response.content)
        response.close()        
        return(get_interesting_values(DEBUG_CFG=DEBUG_CFG, jdata=jdata))
    except Exception as error:
        print("An exception occurred:", error)
        return(dict([('valid',False)]))

def get_interesting_values(DEBUG_CFG:dict, jdata) -> dict:
    try:
        meas = dict([
            ('valid',True),
            ('date_time',jdata['system']['date_time']),
            ('power_pos',float(jdata['report']['instantaneous_power']['active']['positive']['total'])),
            ('power_neg',float(jdata['report']['instantaneous_power']['active']['negative']['total'])),
            ('energy_pos',jdata['report']['energy']['active']['positive']['total']),
            ('energy_neg',jdata['report']['energy']['active']['negative']['total']),
            ('energy_pos_t1',jdata['report']['energy']['active']['positive']['t1']),
            ('energy_pos_t2',jdata['report']['energy']['active']['positive']['t2'])
        ])
        #print_values(meas=meas)
        #print("Content:\n", jdata)
    except Exception as error:
        print("Error: json values not as expected:", error)
        meas = dict([('valid',False)])
    
    return(meas)

def print_values(meas:dict):
    print('date time:',meas['date_time'])
    print('current power +:',meas['power_pos'])
    print('current power -:',meas['power_neg'])

    print('energy +:',meas['energy_pos'])
    print('energy -:',meas['energy_neg'])
    
    print('energy + T1:',meas['energy_pos_t1'])
    print('energy + T2:',meas['energy_pos_t2'])
    return
    
def send_message_and_wait_post(DEBUG_CFG:dict, message:dict):
    # about TXVER: integer (range 0 to 9), increases when there is a change on the transmitted value format 
    # 0 is doing GET-communication, 1 uses post to transmit an identifier, values as blob
    # 2 uses authentification with a hash when sending
    if(DEBUG_CFG['wlan'] == 'real'): # not sending anything in simulation
        URL = "https://strommesser.ch/verbrauch/rx_v3.php?TX=pico&TXVER=3"
        transmit_message(DEBUG_CFG=DEBUG_CFG, URL=URL, message=message)

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
    if (not DEBUG_CFG['tx_to_server']):
        return
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    try:
        urlenc = urlencode(message)
        # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue 
        response = request.post(URL, data=urlenc, headers=HEADERS)
        if (response.status_code != 200):
            print("Error: invalid status code. Resetting in 20 seconds...")
            sleep(20)             
            reset() # NB: connection to whatever device is getting lost; complicates debugging        
        #print('Text:'+response.text)
        response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
        return
    except:
        print("Error: request.post did not work. Resetting in 20 seconds...")
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

def tx_to_server(DEBUG_CFG:dict, DEVICE_CFG:dict, meas:dict):
        randNum_hash = get_randNum_hash(DEVICE_CFG)
        meas_string = str(meas['date_time'])+'|'+str(meas['energy_pos'])+'|'+str(meas['energy_neg'])+'|'+str(meas['energy_pos_t1'])+'|'+str(meas['energy_pos_t2'])

        message = dict([
            ('userid', DEVICE_CFG['userid']),
            ('values', meas_string),
            ('randNum', randNum_hash['randNum']),
            ('hash', randNum_hash['hash'])
            ])
        #print(str(message))
        send_message_and_wait_post(DEBUG_CFG=DEBUG_CFG, message=message)
        del randNum_hash, meas_string, message
        return

