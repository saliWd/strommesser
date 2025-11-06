## xx_version_placeholder_xx

from machine import reset # type: ignore
from time import sleep
from hashlib import sha256
from binascii import hexlify, unhexlify
from random import randint
import requests_1 as request # from https://github.com/shariltumin/bit-and-pieces/tree/main/web-client, see also https://github.com/orgs/micropython/discussions/14105
import json
import network # type: ignore (this is a pylance ignore warning directive)
import micropython_ota # type: ignore | using version 2.1.0., install with thonny/tools/packages

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

def getDispYrange(values:list) -> list:
    """returns the range of the given values. extends the range to at least -50 to +50 if the min/max are smaller. returns 2 positive values"""
    minimum = abs(min(min(values),-50))
    maximum = max(max(values),50)
    return [minimum,maximum,(minimum+maximum)]

def json_get_req(DEBUG_CFG:dict, local_ip:str) -> dict:
    URL = 'http://'+ local_ip + '/cm?cmnd=status%2010'
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
        #print("Content:\n", jdata)
        # sanity check, all energy values have to be bigger than 0. Otherwise will try again
        if (
            meas['energy_pos'] <= 0 or 
            meas['energy_neg'] <= 0 or 
            meas['energy_pos_t1'] <= 0 or 
            meas['energy_pos_t2'] <= 0
        ):
            print("Error: at least one energy value is zero")
            meas = dict([('valid',False)])
    except Exception as error:
        print("Error: json values not as expected: ", error)
        meas = dict([('valid',False)])
    
    return(meas)

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
        return(transmit_message(message=message, useWdt=useWdt, wdt=wdt))
    else: # wlan simulated
        return(dict([('serverOk', 1),
                     ('brightness', 80), # just some different values
                     ('minValCon', 200),
                     ('maxValGen', 2000),
                     ('earn', -0.27)]))    
    

def transmit_message(message:dict, useWdt:bool, wdt) -> dict:
    URL = "https://strommesser.ch/verbrauch/pico2w_v4.php?TX=pico&TXVER=3"
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    failureCount = 0
    while failureCount < 3:
        feed_wdt(useWdt=useWdt,wdt=wdt)
        try:
            urlenc = urlencode(message)
            #print(URL) #print(message)
            feed_wdt(useWdt=useWdt,wdt=wdt)
            response = request.post(URL, data=urlenc, headers=HEADERS) # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue
            feed_wdt(useWdt=useWdt,wdt=wdt)
            if (response.status_code == 200):
                #print('Text:'+response.text)
                answer = response.text
                response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
                feed_wdt(useWdt=useWdt,wdt=wdt)
                valueArray = answer.split('|',4) # maxsplit=4, returns up to 5 elements
                valueArrayLen = len(valueArray)
                if (valueArrayLen > 3 ):
                    return(dict([('serverOk', int(valueArray[0])),
                                 ('brightness', int(valueArray[1])),
                                 ('minValCon', int(valueArray[2])),
                                 ('maxValGen', int(valueArray[3])),
                                 ('earn', float(valueArray[4]))])) # two decimals, pos or negative
                else:
                    print("Error: server response not as expected. Length of return array: "+str(valueArrayLen)+". FailureCount: "+str(failureCount))
                    failureCount += 1
            else:
                print("Error: invalid status code:"+str(response.status_code)+". FailureCount: "+str(failureCount))
                response.close()
                failureCount += 1
        except Exception as error:
            print("Error: request.post did not work. Error:", error)        
            failureCount += 1
        feed_wdt(useWdt=useWdt,wdt=wdt)
        sleep(4) # wait in between the loops
    
    # while loop has passed, did not work several times, do a reset now
    print("Error: failure count too high:"+str(failureCount)+". Resetting in 20 seconds or with the watchdog...")
    sleep(20) # add a bit of debug possibility. NB: # this will trigger the watchdog timer (if enabled) and reset as well    
    reset() # NB: connection to whatever device is getting lost; complicates debugging
    return(dict([('serverOk', 0)])) # this return will never be executed


# is called once before while loop
def wlan_init(DEBUG_CFG:dict, WLAN_CFG:dict):
    if(DEBUG_CFG['wlan'] == 'simulated'):
        print('WLAN connection is simulated...')
        return(1) # no meaningful return value

    wlanStatus = 0
    waitCounter = 0
    wlan = 0
    while wlanStatus != network.STAT_GOT_IP: # STAT_GOT_IP = 3, STAT_CONNECTING = 1
        print('waiting for connection...WLAN Status: '+str(wlanStatus)+'. Counter: '+str(waitCounter))
        wlan = network.WLAN(network.WLAN.IF_STA)
        wlan.active(True) # activate it. NB: disabling does not work correctly
        sleep(2)
        wlan_pw = unhexlify(WLAN_CFG['pw'].encode()).decode() # change into byte stream and unhex it; then change it into string        
        wlan.connect(WLAN_CFG['ssid'], wlan_pw)
        sleep(2)
        wlanStatus = wlan.status()
        if wlanStatus == network.STAT_GOT_IP: # success
            wlanIfconfig = wlan.ifconfig()
            print('connected. IP: ' + wlanIfconfig[0])
            return(wlan) # type: ignore        

        waitCounter += 1
        sleep(2)
    return(wlan) # this should never happen


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
    """a helper function, not used in the code itself"""
    hex_input = hexlify(input.encode()) # hex the bytestream of the string
    print(hex_input.decode())

def tx_to_server(DEBUG_CFG:dict, DEVICE_CFG:dict, meas:dict, loopCount:int, useWdt:bool, wdt) -> dict:
        randNum_hash = get_randNum_hash(DEVICE_CFG)
        meas_string = str(meas['date_time'])+'|'+str(meas['energy_pos'])+'|'+str(meas['energy_neg'])+'|'+str(meas['energy_pos_t1'])+'|'+str(meas['energy_pos_t2'])+'|'+str(loopCount)

        message = dict([
            ('userid', DEVICE_CFG['userid']),
            ('values', meas_string),
            ('randNum', randNum_hash['randNum']),
            ('hash', randNum_hash['hash'])
            ])
        #print(str(message))
        feed_wdt(useWdt=useWdt,wdt=wdt)
        settings = server_communication(DEBUG_CFG=DEBUG_CFG, message=message, useWdt=useWdt, wdt=wdt)        
        del randNum_hash, meas_string, message
        return(settings)

def feed_wdt(useWdt:bool, wdt):
    if useWdt:
        wdt.feed() # type: ignore
    return

def do_ota(DEBUG_CFG):
    if (DEBUG_CFG['wlan'] == 'real'): # don't do ota otherwise
        micropython_ota.ota_update(
            host='https://strommesser.ch/ota/',
            project='display',
            filenames=['boot.py', 'main.py', 'function_def.py', 'class_def.py', 'font.af', 'background.png'], # config (and libraries) is not changed
            use_version_prefix=False
        )
    return


