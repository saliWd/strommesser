import network # type: ignore (this is a pylance ignore warning directive)
import urequests # type: ignore
from time import sleep

def urlencode(dictionary:dict):
    urlenc = ""
    for key, val in dictionary.items():
        urlenc += "%s=%s&" %(key,val)
    urlenc = urlenc[:-1] # gets me something like 'val0=23&val1=bla space'
    return(urlenc)

def wlan_connect(wlan):
    sleep(3)
    wlan_ok_flag = wlan.isconnected()
    if(wlan_ok_flag):
        return() # nothing to do
    else: # wlan is not ok
        for i in range(30): # set the time out
            config_wlan = dict([("ssid","strommesser"),("pw","publicPW")])
            wlan.connect(config_wlan['ssid'], config_wlan['pw'])
            sleep(3)
            wlan_ok_flag = wlan.isconnected()
            print("WLAN connected? "+str(wlan_ok_flag)+", loop var: "+str(i)) # debug output
            if (wlan_ok_flag):
                return 
        # timeout, did not manage to get a working WLAN
        from machine import reset # type: ignore
        reset() # NB: connection to whatever device is getting lost; complicates debugging

def sepStrToArr(separatedString:str):
    valueArray = separatedString.split("|") # Format: $valid|$newestConsumption|Y|m|d|H|i|s    
    retVal = dict([
            ('valid', 0),
            ('wattValue', 999),
            ('hour', 99),
            ('max', 405),
            ('brightness', 80)
    ])
    if (len(valueArray) > 9 ):
            retVal["valid"] = int(valueArray[0])
            retVal["wattValue"] = int(valueArray[1])
            retVal["hour"] = int(valueArray[5])
            retVal["max"] = int(valueArray[8])
            retVal["brightness"] = int(valueArray[9])            
    return retVal

wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(3)

while True:       
    message = dict([
        ('userid', '2'),
        ('randNum', 'blablu'),
        ('hash', 'blibli')
        ])
        
    wlan_connect(wlan=wlan) # try to connect to the WLAN. Hangs there if no connection can be made
    
    print('2.0 before sending')
    response = urequests.post(
        'https://strommesser.ch/verbrauch/getRaw_temp.php', 
        data=urlencode(message), 
        headers={'Content-Type':'application/x-www-form-urlencoded'})
    sleep(5)
    print('3.0 after sending')
    print('respCode:'+str(response.status_code))    
    print('3.1 before copying')
    returnText = response.text
    print('3.1 after copying')        
    print(response.text)
    print('3.1 after printing')        
    meas = sepStrToArr(separatedString=response.text)
    response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
    del response

    # normalize the value. Is between 0 and max
    wattValueNonMaxed = meas["wattValue"]    
    meas["wattValue"] = min(meas["wattValue"], meas["max"])
    meas["wattValue"] = max(meas["wattValue"], 0)

    print("normalized watt value: "+str(meas["wattValue"])+", max/bright: "+str(meas["max"])+"/"+str(meas["brightness"]))
    sleep(80)
    