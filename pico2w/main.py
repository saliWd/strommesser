# using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# does not work: Micropython version Raspberry Pico 2 W with Pimoroni libraries 0.0.11
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

from math import sin
from machine import Timer # type: ignore
import requests_1 as request # from https://github.com/shariltumin/bit-and-pieces/tree/main/web-client, see also https://github.com/orgs/micropython/discussions/14105
import json
from pimoroni import RGBLED  # type: ignore (included in uf2 file)
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore

# my own files
import my_config
from my_functions import wlan_init,debug_sleep,debug_print


class RgbLed(object):

    def __init__(self):
        self.led_rgb = RGBLED(6, 7, 8)
        self.timer_rgb = Timer() # no need to specify a number on pico, all SW timers
        self.color = (0,0,0)
        self.rgb   = (0,0,0)
        self.freq = 5
        self.sineX = 0.0
        self.timerIsInitialized = False

    def pulse_cb(self, noIdeaWhyThisIsNeeded):
        if self.sineX < 5.0: # (smaller than 2*pi)
            self.sineX += 0.02
        else:
            self.sineX = 0
        factor = max(sin(self.sineX), 0.0) # not using abs() because I really want the LED to be off for half the time, to clearly distinguish between cons and gen.
        self.rgb = ((int)(factor*self.color[0]),
                    (int)(factor*self.color[1]),
                    (int)(factor*self.color[2]))
        
        self.led_rgb.set_rgb(*(self.rgb))
        
    def control(self, valid:bool, pulsating:bool, color):
        if not valid:
            self.color = (240, 0, 0)
            self.freq = 100
            self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
            self.timerIsInitialized = False # always do a fresh init for the error case. Don't check the isInitialized value
            return

        if pulsating:
            self.color = color
            self.freq = 30
            if not (self.timerIsInitialized):
                self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
                self.timerIsInitialized = True
        else:
            self.timer_rgb.deinit() # not always needed
            self.timerIsInitialized = False
            self.led_rgb.set_rgb(*color)

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

def sepStrToArr(separatedString:str):
    valueArray = separatedString.split("|") # Format: valid|newestCons|Y|m|d|H|i|s|newestGen|ledMaxValGen
    retVal = dict([
            ('valid', 0),
            ('wattCons', 999),
            ('hour', 99),
            ('max', 405),
            ('brightness', 80),
            ('wattGen', 987),
            ('maxGen', 1050)
    ])
    if (len(valueArray) > 11 ):
            retVal["valid"] = int(valueArray[0])
            retVal["wattCons"] = int(valueArray[1])
            retVal["hour"] = int(valueArray[5])
            retVal["max"] = int(valueArray[8])
            retVal["brightness"] = int(valueArray[9])
            retVal["wattGen"] = int(valueArray[10])
            retVal["maxGen"] = int(valueArray[11])
    return retVal

def getBrightness(meas:list)->int:
    """reads the brightness (from the server) and adjusts it during the night"""
    brightness = meas["brightness"]
    if (meas["hour"] > 20) or (meas["hour"] < 6):
        brightness = int(0.25 * meas["brightness"]) # darker from 21:00 to 05:59. rounded down
    return brightness

def getDispYrange(values:list) -> list:
    """returns the range of the given values. extends the range to at least -50 to +50 if the min/max are smaller. returns 2 positive values"""
    minimum = abs(min(min(values),-50))
    maximum = max(max(values),50)
    return [minimum,maximum,(minimum+maximum)]

# get request might be unstable (depends on internet connection)
def json_get_request(DEBUG_CFG:dict) -> dict:
    returnVal = dict([('valid',False)]) # minimal return value. Will be overwritten in more meaningful cases
    
    URL = "http://192.168.178.47/api/v1/report" # on the local net
    if DEBUG_CFG['json_data'] == 'web': # can be 'local_net'|'web'|'file'
        URL = "https://strommesser.ch/json_long.php"
    elif DEBUG_CFG['json_data'] == 'file':         
        return(get_interesting_values(jdata=my_config.debug_jdata()))
    try:
        response = request.get(url=URL, timeout=9)
        if (response.status_code != 200):
            print('status wrong: ',response.status_code)
            return(returnVal)
        jdata = json.loads(response.content)
        response.close()        
        return(get_interesting_values(jdata=jdata))
    except Exception as error:
        print("An exception occurred:", error)
        return(returnVal)

def get_interesting_values(jdata) -> dict:
    meas = dict([
        ('valid',True),
        ('date_time',jdata['system']['date_time']),
        ('power_pos',jdata['report']['instantaneous_power']['active']['positive']['total']),
        ('power_neg',jdata['report']['instantaneous_power']['active']['negative']['total']),
        ('energy_pos',jdata['report']['energy']['active']['positive']['total']),
        ('energy_neg',jdata['report']['energy']['active']['negative']['total']),
        ('energy_pos_t1',jdata['report']['energy']['active']['positive']['t1']),
        ('energy_pos_t2',jdata['report']['energy']['active']['positive']['t2'])])
    # print("Content:\n", jdata)
    print_values(meas=meas)
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
    

DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
DEVICE_CFG = my_config.get_device_config()
wlan = wlan_init(DEBUG_CFG=DEBUG_CFG)

LOOP_COUNT_MAX = 3 # program runs for this many loops
LOOP_SLEEP_SEC = 5 # pause between loops


display = PicoGraphics(display=DISPLAY_PICO_DISPLAY, rotate=0)
display.set_backlight(0.5)
display.set_font("sans")
WIDTH, HEIGHT = display.get_bounds() # 240x135
BLACK = display.create_pen(0, 0, 0)
TEXT_BG_GEN = display.create_pen(170, 255, 170)
TEXT_BG_CONS = display.create_pen(255, 170, 170)
BAR_WIDTH = 5
wattVals = []
# fills the screen with black
display.set_pen(BLACK)
display.clear()
display.update()
rgb_led = RgbLed()
rgb_led.control(valid=False, pulsating=False, color=(255,0,0))



loopCount = 0
while True:
    debug_print(DEBUG_CFG=DEBUG_CFG, text='loop: '+str(loopCount))
    meas = json_get_request(DEBUG_CFG=DEBUG_CFG)
    if not meas['valid']:
        print('get request did not work')

    
    wattVal = (-1 * meas['power_pos']) + meas['power_neg'] # cons is negative, gen positive. 0 is treated as gen   
    debug_print(DEBUG_CFG=DEBUG_CFG, text='wattValue: '+str(wattVal))


    debug_sleep(DEBUG_CFG=DEBUG_CFG,time=LOOP_SLEEP_SEC)
    loopCount += 1
    if loopCount > LOOP_COUNT_MAX:
        break
    
    

print('done')

