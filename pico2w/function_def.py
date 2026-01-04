## xx_version_placeholder_xx

from machine import reset # type: ignore
from time import sleep
from hashlib import sha256
from binascii import hexlify, unhexlify
from random import randint
from urequests import post, get # type: ignore
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

def getDispYrange(values:list, BAR_HEIGHT:int) -> float:
    """returns the range of the given values. returns a float value scaled with bar height"""
    minimum = abs(min(min(values),0)) # 0 or positive
    maximum = max(max(values),0) # 0 or positive
    retMinSize = max(minimum,maximum,10) # 10 or bigger
    return float(BAR_HEIGHT) / float(retMinSize)

def json_get_req(DEBUG_CFG:dict, local_ip:str, log, wd) -> dict:
    URL = 'http://'+ local_ip + '/cm?cmnd=status%2010'
    # Maybe to do: could also use http://gplugm.local/cm?cmnd=status%2010. Does not help in my case though where I have two gplugm
    
    if DEBUG_CFG['json_data'] == 'web': # can be 'local_net'|'web'|'file'
        URL = "https://strommesser.ch/pages/json.php?reader=gplug"
    elif DEBUG_CFG['json_data'] == 'file':
        return(get_interesting_values(jdata=get_debug_jdata(),log=log))
    # get request might be unstable (depends on internet connection)
    try:
        feed_wd(wd=wd)
        response = get(url=URL, timeout=5) # watchdog is 8.x seconds
        feed_wd(wd=wd)
        if (response.status_code != 200):
            runLog(file=log,string='WARN|function_def|json_get_req: Status of response is wrong')
            response.close()
            return(dict([('valid',False)]))
        jdata = json.loads(response.content)
        response.close()
        feed_wd(wd=wd)
        return(get_interesting_values(jdata=jdata,log=log))
    except:
        runLog(file=log,string='WARN|function_def|json_get_req: Exception at request.get')
        return(dict([('valid',False)]))

def get_debug_jdata() -> dict:
    consumption = randint(0, 30000)
    consumption = consumption / 1000.0 # range between -30.000 and 30.000 (kW)
    po, pi = 0.000, consumption # pi = consuming, po = generating
    if randint(0,1) == 0: # 50%, pos or neg
        pi, po = 0.000, consumption
    return({"StatusSNS":{"Time":"2025-04-26T22:49:54","z":{"SMid":"72913313","Pi":pi,"Po":po,"I1":0.35,"I2":0.42,"I3":0.12,"Ei":168.754,"Eo":604.610,"Ei1":130.675,"Ei2":38.070,"Eo1":114.819,"Eo2":489.779,"Q5":154.927,"Q6":10.593,"Q7":84.753,"Q8":121.569}}})

def runLog(file, string:str) -> None:
    try:
        file.write(string+"\n") # add a newline
        file.flush() # make sure it's written before any reset happens
    except: # most probably storage is full
        log = open('run.log', 'w') # overwrite old file
        log.write(string+"\n") # the original message
        string = 'WARN|function_def|runLog: cannot append to log. Starting a new file...'
        log.write(string+"\n") # add a newline
        log.flush()
        print(string)
        sleep(2)
        reset() # old file handle is stale now. starting new
    print(string)
    return

def get_interesting_values(jdata, log) -> dict:
    try:
        meas = dict([
            ('valid',True),
            ('date_time',jdata['StatusSNS']['Time']),
            ('power_pos',float(jdata['StatusSNS']['z']['Pi'])),
            ('power_neg',float(jdata['StatusSNS']['z']['Po'])),
            ('energy_pos',float(jdata['StatusSNS']['z']['Ei'])),
            ('energy_neg',float(jdata['StatusSNS']['z']['Eo'])),
            ('energy_pos_t1',float(jdata['StatusSNS']['z']['Ei1'])),
            ('energy_pos_t2',float(jdata['StatusSNS']['z']['Ei2']))
        ])
        #print("Content:\n", jdata)
        # sanity check: energy values have to be bigger than 0, power either 0 or bigger. Otherwise will try again
        if (
            meas['power_pos'] < 0 or 
            meas['power_neg'] < 0 or 
            meas['energy_pos'] <= 0 or 
            meas['energy_neg'] <= 0 or 
            meas['energy_pos_t1'] <= 0 or 
            meas['energy_pos_t2'] <= 0
        ):
            runLog(file=log,string='WARN|function_def|get_interesting_values: sanity check for power and energy values not ok')
            meas = dict([('valid',False)])
    except:
        runLog(file=log,string='WARN|function_def|get_interesting_values: json values not as expected')
        meas = dict([('valid',False)])
    return(meas)

def getBrightness(setting:int, time:str, wattVal:int, log) -> tuple:
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
        return (brightness,pulsed)    
    except:
        runLog(file=log,string='WARN|function_def|getBrightness: time format not as expected')
        return(0,False) # just some value
   
def transmit_message(message:dict, wd, log) -> dict:
    URL = "https://strommesser.ch/verbrauch/pico2w_v5.php?TX=pico&TXVER=5"
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    failureCount = 0
    while failureCount < 3:
        feed_wd(wd=wd)
        try:
            urlenc = urlencode(message)
            #print(URL) #print(message)
            feed_wd(wd=wd)
            response = post(URL, data=urlenc, headers=HEADERS, timeout=5) # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue
            feed_wd(wd=wd)
            if (response.status_code == 200):
                #print('Text:'+response.text)
                answer = response.text
                response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
                feed_wd(wd=wd)
                valueArray = answer.split('|',4) # maxsplit=4, returns up to 5 elements
                valueArrayLen = len(valueArray)
                if (valueArrayLen > 3 ):
                    return(dict([('serverOk', int(valueArray[0])),
                                 ('brightness', int(valueArray[1])),
                                 ('minValCon', int(valueArray[2])),
                                 ('maxValGen', int(valueArray[3])),
                                 ('earn', float(valueArray[4])), # two decimals, pos or negative
                                 ('fromServer',True)])) 
                else:
                    runLog(file=log,string='WARN|function_def|transmit_message: server response not as expected. Length of return array: '+str(valueArrayLen)+'. FailureCount: '+str(failureCount))
                    failureCount += 1
            else:
                runLog(file=log,string='WARN|function_def|transmit_message: invalid status code:'+str(response.status_code)+'. FailureCount: '+str(failureCount))
                response.close()
                failureCount += 1
        except:
            runLog(file=log,string='WARN|function_def|transmit_message: Request.post did not work. Trying again...')
            failureCount += 1
        feed_wd(wd=wd)
        sleep(4) # wait in between the loops
    
    # while loop has passed, did not work several times, do a reset now
    runLog(file=log,string='ERROR|function_def|transmit_message: failure count too high:'+str(failureCount)+'. Resetting in 20 seconds.')
    sleep(20) # add a bit of debug possibility. NB: # this will trigger the watchdog timer (if enabled) and reset as well    
    reset() # NB: connection to whatever device is getting lost; complicates debugging
    return(dict([('serverOk',0),('brightness',0),('minValCon',0),('maxValGen',1),('earn',0.0),('fromServer',False)])) # this return will never be executed


# is called once before while loop
def wlan_init(DEBUG_CFG:dict, WLAN_CFG:dict, wd, log):
    if(DEBUG_CFG['wlan'] == 'simulated'):
        runLog(file=log,string='STAT|function_def|wlan_init: WLAN connection is simulated...')
        return(1) # no meaningful return value

    wlanStatus = 0
    waitCounter = 0
    wlan = 0
    while wlanStatus != network.STAT_GOT_IP: # STAT_GOT_IP = 3, STAT_CONNECTING = 1
        runLog(file=log,string='STAT|function_def|wlan_init: waiting for connection...WLAN Status: '+str(wlanStatus)+'. Counter: '+str(waitCounter))
        wlan = network.WLAN(network.WLAN.IF_STA)
        wlan.active(True) # activate it. NB: disabling does not work correctly
        feed_wd(wd=wd)
        sleep(2)
        wlan_pw = unhexlify(WLAN_CFG['pw'].encode()).decode() # change into byte stream and unhex it; then change it into string        
        wlan.connect(WLAN_CFG['ssid'], wlan_pw)
        feed_wd(wd=wd)
        sleep(2)
        wlanStatus = wlan.status()
        if wlanStatus == network.STAT_GOT_IP: # success
            wlanIfconfig = wlan.ifconfig()
            runLog(file=log,string='STAT|function_def|wlan_init: connected. IP: ' + wlanIfconfig[0])
            return(wlan) # type: ignore

        waitCounter += 1
        feed_wd(wd=wd)
        sleep(2)
    return(wlan) # this should never happen

# is called once before while loop
def wlan_check(DEBUG_CFG:dict, wd, wlan, log) -> bool:
    if(DEBUG_CFG['wlan'] == 'simulated'):        
        return(True)

    waitCounter = 0
    while wlan.status() != network.STAT_GOT_IP: # STAT_GOT_IP = 3, STAT_CONNECTING = 1
        feed_wd(wd=wd)
        if waitCounter > 8: # a time out
            runLog(file=log,string='ERROR|function_def|wlan_check: did loose wlan connection. Trying to re-init the connection.')
            wlan.active(False) # does not really change anything though
            return(False)
        runLog(file=log,string='WARN|function_def|wlan_check: waiting for connection...WLAN Status: '+str(wlan.status())+'. Counter: '+str(waitCounter))
        feed_wd(wd=wd)
        sleep(2)
        feed_wd(wd=wd)
        sleep(2)
        feed_wd(wd=wd)
        waitCounter += 1
    return(True) # this should never happen

def urlencode(dictionary:dict) -> str:
    urlenc = ""
    for key, val in dictionary.items():
        urlenc += "%s=%s&" %(key,val)
    urlenc = urlenc[:-1] # gets me something like 'val0=23&val1=bla space'
    return(urlenc)

def get_randNum_hash(device_config) -> dict:
    rand_num = randint(1, 10000)
    myhash = sha256(str(rand_num)+device_config['post_key'])
    hashString = hexlify(myhash.digest())
    returnVal = dict([
        ('randNum', rand_num),
        ('hash', hashString.decode())
    ])
    return(returnVal)

def hexlify_wlan(input:str) -> None:
    """a helper function, not used in the code itself"""
    hex_input = hexlify(input.encode()) # hex the bytestream of the string
    print(hex_input.decode())
    return

def tx_to_server(DEBUG_CFG:dict, DEVICE_CFG:dict, meas:dict, loopCount:int, wd, log) -> dict:
        randNum_hash = get_randNum_hash(DEVICE_CFG)
        meas_string = str(meas['date_time'])+'|'+str(meas['energy_pos'])+'|'+str(meas['energy_neg'])+'|'+str(loopCount)

        message = dict([
            ('userid', DEVICE_CFG['userid']),
            ('values', meas_string),
            ('randNum', randNum_hash['randNum']),
            ('hash', randNum_hash['hash'])
            ])
        #print(str(message))
        feed_wd(wd=wd)
        if(DEBUG_CFG['wlan'] == 'real' and DEBUG_CFG['server_txrx']): # not sending anything in simulation or when server_txrx is disabled
            settings = transmit_message(message=message,wd=wd,log=log)
        else: # wlan simulated
            settings = dict([('serverOk', 1),
                             ('brightness', 80), # just some different values
                             ('minValCon', 200),
                             ('maxValGen', 2000),
                             ('earn', -0.27),
                             ('fromServer',True)])
        del randNum_hash, meas_string, message
        return(settings)

def feed_wd(wd:list) -> None:
    if wd[0]: # use watchdog
        wd[1].feed()
    return

def do_ota(DEBUG_CFG) -> None:
    if (DEBUG_CFG['wlan'] == 'real'): # don't do ota otherwise
        micropython_ota.ota_update(
            host='https://strommesser.ch/ota/',
            project='display',
            filenames=['boot.py', 'main.py', 'function_def.py', 'class_def.py', 'font.af', 'background.png'], # config (and libraries) is not changed
            use_version_prefix=False
        )
    return


