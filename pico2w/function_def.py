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
    else: v = int(255*v); return (v, v, v)

def val_to_rgb(val:int, minValCon:int, maxValGen:int, led_brightness:int)-> list: # goes from red to blue
    # value has a range from -minValCons to +maxValGen. minVal and maxVal are both positive numbers but minVal may be treated as negative
    if val < 0: val = val * 2 # get a higher color 'resolution' for negative values
    val = val + 2*minValCon # bring it to the range 0..(min+max)
    h = float(val) / float(1.4*(2*minValCon+maxValGen)) # h value makes a 'circle'. This means 0 degree is the same as 360°. -> Need to limit it (but not to 180°, just less than 360)
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

def print_loopCount(display, BLACK, loopCount:str):
    x = 10
    y = 120
    display.set_pen(BLACK) # black border around the white text
    display.text(loopCount, x-1, y, scale=1.0)
    display.text(loopCount, x+1, y, scale=1.0)
    display.text(loopCount, x, y-1, scale=1.0)
    display.text(loopCount, x, y+1, scale=1.0)
    display.set_pen(display.create_pen(255, 255, 255)) # white
    display.text(loopCount, x, y, scale=1.0)

def getDispYrange(values:list) -> list:
    """returns the range of the given values. extends the range to at least -50 to +50 if the min/max are smaller. returns 2 positive values"""
    minimum = abs(min(min(values),-50))
    maximum = max(max(values),50)
    return [minimum,maximum,(minimum+maximum)]

def json_get_req(DEBUG_CFG:dict, DEVICE_CFG:dict, READER:str) -> dict:
    URL = 'http://'+DEVICE_CFG['local_ip']
    if READER == 'whatwatt':
        URL = URL + '/api/v1/report' # on the local net
    else: # gplug. Maybe to do: could also use http://gplugm.local/cm?cmnd=status%2010. Does not help in my case though where I have two gplugm
        URL =  URL + '/cm?cmnd=status%2010'

    if DEBUG_CFG['json_data'] == 'web': # can be 'local_net'|'web'|'file'
        URL = "https://strommesser.ch/pages/json.php?reader=READER"
    elif DEBUG_CFG['json_data'] == 'file':         
        return(get_interesting_values(jdata=get_debug_jdata(READER=READER), READER=READER))
    # get request might be unstable (depends on internet connection)
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(dict([('valid',False)]))
        jdata = json.loads(response.content)
        response.close()        
        return(get_interesting_values(jdata=jdata, READER=READER))
    except Exception as error:
        print("An exception occurred:", error)
        return(dict([('valid',False)]))

def get_debug_jdata(READER:str):
    if READER == 'whatwatt':
        return({"report":{"id":292,"interval":2.737,"date_time":"2025-04-22T18:32:05Z","instantaneous_power":{"active":{"positive":{"total":0},"negative":{"total":1.803}}},"energy":{"active":{"positive":{"total":167.508,"t1":130.297,"t2":37.202},"negative":{"total":575.932,"t1":107.105,"t2":468.817}},"reactive":{"imported":{"inductive":{"total":152.881},"capacitive":{"total":10.117}},"exported":{"inductive":{"total":78.124},"capacitive":{"total":114.091}}}},"conv_factor":1},"meter":{"status":"OK","interface":"MBUS","protocol":"DLMS","id":"72913313","vendor":"Landis+Gyr","prefix":"LGZ"},"system":{"id":"ECC9FF5C80B0","date_time":"2025-04-22T17:32:12Z","boot_id":"E766FCAC","time_since_boot":1345}})
    else:
        return({"StatusSNS":{"Time":"2025-04-26T22:49:54","z":{"SMid":"72913313","Pi":0.010,"Po":0.000,"I1":0.35,"I2":0.42,"I3":0.12,"Ei":168.754,"Eo":604.610,"Ei1":130.675,"Ei2":38.070,"Eo1":114.819,"Eo2":489.779,"Q5":154.927,"Q6":10.593,"Q7":84.753,"Q8":121.569}}})

def get_interesting_values(jdata, READER:str) -> dict:
    try:
        if READER == 'whatwatt':
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
        else: # gplug
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

# sends the measurement data and gets the settings
def server_communication(DEBUG_CFG:dict, message:dict):
    if(DEBUG_CFG['wlan'] == 'real' and DEBUG_CFG['server_txrx']): # not sending anything in simulation or when server_txrx is disabled
        valueString=transmit_message(message=message)
    else: # wlan simulated
        valueString='1|80|200|2000' # serverok|ledBrightness|ledMinValCon|ledMaxValGen
    return(sepStrToArr(valueString=valueString))
    

def transmit_message(message:dict):
    URL = "https://strommesser.ch/verbrauch/pico2w_v3.php?TX=pico&TXVER=3"
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    failureCount = 0
    while failureCount < 3:
        try:
            urlenc = urlencode(message)
            #print(URL)
            #print(message)
            response = request.post(URL, data=urlenc, headers=HEADERS) # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue
            if (response.status_code == 200):
                #print('Text:'+response.text)
                answer = response.text
                response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
                return(answer)
            else:
                print("Error: invalid status code:"+str(response.status_code)+". FailureCount: "+str(failureCount))
                response.close()
                failureCount += 1
        except:
            print("Error: request.post did not work")
            failureCount += 1
        sleep(5) # wait in between the loops
    
    # while loop has passed, did not work several times, do a reset now
    print("Error: failure count too high:"+str(failureCount)+". Resetting in 20 seconds...")
    sleep(20) # add a bit of debug possibility
    reset() # NB: connection to whatever device is getting lost; complicates debugging
    return # this return will never be executed

def sepStrToArr(valueString:str):
    valueArray = valueString.split('|')
    if (len(valueArray) > 2 ):
        return(dict([
            ('valid',True),
            ('serverOk', int(valueArray[0])),
            ('brightness', int(valueArray[1])),
            ('minValCon', int(valueArray[2])),
            ('maxValGen', int(valueArray[3]))
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
        
        waitCounter += 1
        sleep(2)

    wlanIfconfig = wlan.ifconfig()
    print('connected. IP: ' + wlanIfconfig[0])
    sleep(2)
    return(wlan)

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

def tx_to_server(DEBUG_CFG:dict, DEVICE_CFG:dict, meas:dict, settings:dict) -> dict:
        randNum_hash = get_randNum_hash(DEVICE_CFG)
        meas_string = str(meas['date_time'])+'|'+str(meas['energy_pos'])+'|'+str(meas['energy_neg'])+'|'+str(meas['energy_pos_t1'])+'|'+str(meas['energy_pos_t2'])

        message = dict([
            ('userid', DEVICE_CFG['userid']),
            ('values', meas_string),
            ('randNum', randNum_hash['randNum']),
            ('hash', randNum_hash['hash'])
            ])
        #print(str(message))
        new_settings = server_communication(DEBUG_CFG=DEBUG_CFG, message=message)
        if (new_settings['valid']):
            settings = new_settings # otherwise keep the old settings
        del randNum_hash, meas_string, message
        return(settings)

