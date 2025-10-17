## xx_version_placeholder_xx
# using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# does not work: Micropython version Raspberry Pico 2 W with Pimoroni libraries 0.0.11
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

from machine import reset # type: ignore
from time import sleep
from hashlib import sha256
from binascii import hexlify, unhexlify
from random import randint
import requests_1 as request # from https://github.com/shariltumin/bit-and-pieces/tree/main/web-client, see also https://github.com/orgs/micropython/discussions/14105
import json
import gc
import network # type: ignore (this is a pylance ignore warning directive)

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
        return(0,0,0) # default return statement
    else: 
        v = int(255*v); 
        return (v, v, v)

def val_to_rgb(val:int, minValCon:int, maxValGen:int, led_brightness:int)-> list: # goes from red to blue
    # value has a range from -minValCons to +maxValGen. minVal and maxVal are both positive numbers but minVal may be treated as negative
    if val < 0: val = val * 2 # get a higher color 'resolution' for negative values
    val = val + 2*minValCon # bring it to the range 0..(min+max)
    h = float(val) / float(1.4*(2*minValCon+maxValGen)) # h value makes a 'circle'. This means 0 degree is the same as 360°. -> Need to limit it (but not to 180°, just less than 360)
    a = float(led_brightness) / float(255)
    return list(hsva_to_rgb(h, 1.0, 1.0, a))

def right_align(value4digits:int)->int:
    if value4digits < 10:
        return 3
    if value4digits < 100:
        return 2
    if value4digits < 1000:
        return 1
    return 0

def getDispYrange(values:list) -> list:
    """returns the range of the given values. extends the range to at least -50 to +50 if the min/max are smaller. returns 2 positive values"""
    minimum = abs(min(min(values),-50))
    maximum = max(max(values),50)
    return [minimum,maximum,(minimum+maximum)]

def json_get_req(DEBUG_CFG:dict, DEVICE_CFG:dict) -> dict:
    URL = 'http://'+ DEVICE_CFG['local_ip'] + '/cm?cmnd=status%2010'
    # Maybe to do: could also use http://gplugm.local/cm?cmnd=status%2010. Does not help in my case though where I have two gplugm
    
    if DEBUG_CFG['json_data'] == 'web': # can be 'local_net'|'web'|'file'
        URL = "https://strommesser.ch/pages/json.php?reader=gplug"
    elif DEBUG_CFG['json_data'] == 'file':         
        return(get_interesting_values(jdata=get_debug_jdata()))
    # get request might be unstable (depends on internet connection)
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(dict([('valid',False)]))
        jdata = json.loads(response.content)
        response.close()        
        return(get_interesting_values(jdata=jdata))
    except Exception as error:
        print("An exception occurred:", error)
        return(dict([('valid',False)]))

def get_debug_jdata():
    return({"StatusSNS":{"Time":"2025-04-26T22:49:54","z":{"SMid":"72913313","Pi":0.027,"Po":0.000,"I1":0.35,"I2":0.42,"I3":0.12,"Ei":168.754,"Eo":604.610,"Ei1":130.675,"Ei2":38.070,"Eo1":114.819,"Eo2":489.779,"Q5":154.927,"Q6":10.593,"Q7":84.753,"Q8":121.569}}})

def get_interesting_values(jdata) -> dict:
    try:
        meas = dict([
            ('valid',True),
            ('date_time',jdata['StatusSNS']['Time']),
            ('power_pos',float(jdata['StatusSNS']['z']['Pi'])),
            ('power_neg',float(jdata['StatusSNS']['z']['Po'])),
            ('energy_pos',jdata['StatusSNS']['z']['Ei']),
            ('energy_neg',jdata['StatusSNS']['z']['Eo']),
            ('energy_pos_t1',jdata['StatusSNS']['z']['Ei1']),
            ('energy_pos_t2',jdata['StatusSNS']['z']['Ei2'])
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

def getBrightness(setting:int, time:str, wattVal:int):
    """adjusts the brightness (from the server) during the night and depending on the measured value"""
    pulsed = False
    if (wattVal == 0): 
        return(0,pulsed) # disable LED when 0 consumption
    if (wattVal < 0): 
        brightness=int(setting/2) # when consuming energy the led is shining constantly and thus quite bright
    else:
        pulsed = True
        brightness = setting

    # time is either 2025-04-22T18:32:05Z or 2025-04-26T22:49:54 (with or without Z)
    try:
        date_time_split = time.split('T')
        hour_split = date_time_split[1].split(':')
        hour = int(hour_split[0])
        if (hour > 20) or (hour < 6):
            brightness = int(0.25 * setting) # darker from 21:00 to 05:59. rounded down
    except:
        print('Error: time format not as expected')
    return (brightness,pulsed)

# sends the measurement data and gets the settings
def server_communication(DEBUG_CFG:dict, message:dict, useWdt:bool, wdt) -> dict:
    if(DEBUG_CFG['wlan'] == 'real' and DEBUG_CFG['server_txrx']): # not sending anything in simulation or when server_txrx is disabled
        valueString=transmit_message(message=message, useWdt=useWdt, wdt=wdt)
    else: # wlan simulated
        valueString='1|80|200|2000|-0.27' # serverok|ledBrightness|ledMinValCon|ledMaxValGen|earn
    return(sepStrToArr(valueString=valueString))
    

def transmit_message(message:dict, useWdt:bool, wdt) -> str:
    URL = "https://strommesser.ch/verbrauch/pico2w_v4.php?TX=pico&TXVER=3"
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    failureCount = 0
    feed_wdt(useWdt=useWdt,wdt=wdt)
    while failureCount < 3:
        try:
            urlenc = urlencode(message)
            #print(URL)
            #print(message)
            feed_wdt(useWdt=useWdt,wdt=wdt)
            response = request.post(URL, data=urlenc, headers=HEADERS) # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue
            feed_wdt(useWdt=useWdt,wdt=wdt)
            if (response.status_code == 200):
                #print('Text:'+response.text)
                answer = response.text
                response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
                feed_wdt(useWdt=useWdt,wdt=wdt)
                return(answer)
            else:
                print("Error: invalid status code:"+str(response.status_code)+". FailureCount: "+str(failureCount))
                response.close()                
                failureCount += 1
        except Exception as error:
            print("Error: request.post did not work. Error:", error)        
            failureCount += 1
        feed_wdt(useWdt=useWdt,wdt=wdt)    
        sleep(5) # wait in between the loops
        feed_wdt(useWdt=useWdt,wdt=wdt)
    
    # while loop has passed, did not work several times, do a reset now
    print("Error: failure count too high:"+str(failureCount)+". Resetting in 20 seconds or with the watchdog...")
    sleep(20) # add a bit of debug possibility. NB: # this will trigger the watchdog timer (if enabled) and reset as well    
    reset() # NB: connection to whatever device is getting lost; complicates debugging
    return('bla') # this return will never be executed

def sepStrToArr(valueString:str) -> dict:
    valueArray = valueString.split('|')
    if (len(valueArray) > 3 ):
        return(dict([
            ('valid',True),
            ('serverOk', int(valueArray[0])),
            ('brightness', int(valueArray[1])),
            ('minValCon', int(valueArray[2])),
            ('maxValGen', int(valueArray[3])),
            ('earn', float(valueArray[4])) # two decimals, pos or negative
        ]))
    else:
        return (dict([('valid',False)]))

def debug_sleep(DEBUG_CFG:dict, time:int):
    if(DEBUG_CFG['sleep'] == 'short'): # minimize wait times by sleeping only one second instead of the normal amount
        time = 1
    sleep(time)
    return

# is called once before while loop
def wlan_init(DEBUG_CFG:dict, WLAN_CFG:dict):
    if(DEBUG_CFG['wlan'] == 'simulated'):
        print('WLAN connection is simulated...')
        return(1) # no meaningful return value

    wlanStatus = 0
    waitCounter = 0
    wlan = network.WLAN(network.STA_IF)
    while wlanStatus != 3:
        print('waiting for connection...WLAN Status: '+str(wlanStatus)+'. Counter: '+str(waitCounter))
        wlan = network.WLAN(network.STA_IF)
        wlan.active(True) # activate it. NB: disabling does not work correctly
        sleep(2)
        wlan_pw = unhexlify(WLAN_CFG['pw'].encode()).decode() # change into byte stream and unhex it; then change it into string        
        wlan.connect(WLAN_CFG['ssid'], wlan_pw)
        wlanStatus = wlan.status()
        # need to restart all, otherwise the status is always constant
        if wlanStatus != 3:
            wlan.active(False)
            del wlan
            gc.collect()
        else: # status did change to 3 just above
            wlanIfconfig = wlan.ifconfig()
            print('connected. IP: ' + wlanIfconfig[0])
            sleep(2)
            return(wlan) # type: ignore        

        waitCounter += 1
        sleep(2)
    return(0) # this should never happen


# is called in every while loop
def wlan_conn_check(DEBUG_CFG:dict, WLAN_CFG:dict, wlan):
    if (DEBUG_CFG['wlan'] == 'simulated'):
        return(wlan)
    if(wlan.isconnected()):
        return(wlan) # nothing to do
    else:
        wlan.active(False)
        del wlan
        gc.collect() # garbage collection
        wlanNew = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG) # call the init
        return(wlanNew)

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

def hexlify_wlan(input:str):
    hex_input = hexlify(input.encode()) # hex the bytestream of the string
    print(hex_input.decode())

def tx_to_server(DEBUG_CFG:dict, DEVICE_CFG:dict, meas:dict, settings:dict, useWdt:bool, wdt) -> dict:
        randNum_hash = get_randNum_hash(DEVICE_CFG)
        meas_string = str(meas['date_time'])+'|'+str(meas['energy_pos'])+'|'+str(meas['energy_neg'])+'|'+str(meas['energy_pos_t1'])+'|'+str(meas['energy_pos_t2'])

        message = dict([
            ('userid', DEVICE_CFG['userid']),
            ('values', meas_string),
            ('randNum', randNum_hash['randNum']),
            ('hash', randNum_hash['hash'])
            ])
        #print(str(message))
        new_settings = server_communication(DEBUG_CFG=DEBUG_CFG, message=message, useWdt=useWdt, wdt=wdt)
        if (new_settings['valid']):
            settings = new_settings # otherwise keep the old settings
        del randNum_hash, meas_string, message
        return(settings)

def feed_wdt(useWdt:bool, wdt):
    if useWdt:
        wdt.feed() # type: ignore
    return


