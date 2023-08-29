import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
from machine import Timer # type: ignore
from pimoroni import RGBLED  # type: ignore
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore
from math import sin

# my own files
import my_config
from my_functions import debug_print, debug_sleep, wlan_connect, get_randNum_hash, transmit_message

def send_message_get_response(DBGCFG:dict, message:dict):
    URL = "https://strommesser.ch/verbrauch/getRaw.php?TX=pico&TXVER=2"
    #      valid|newestCons|Y|m|d|H|i|s|ledMaxValue|ledBrightness|newestGen|ledMaxValGen
    SIM_STR = "1|57|2023|01|27|18|22|09|500|100|257|700"
    if (DBGCFG["wlan_sim"]):
        return(sepStrToArr(separatedString=SIM_STR))
    
    returnText = transmit_message(DBGCFG=DBGCFG, URL=URL, message=message)
    return(sepStrToArr(separatedString=returnText))

DBGCFG = my_config.get_debug_settings() # debug stuff
LOOP_WAIT_TIME = 80
wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(1)

device_config = my_config.get_device_config()

display = PicoGraphics(display=DISPLAY_PICO_DISPLAY, rotate=0)
display.set_backlight(0.5)
display.set_font("sans")
WIDTH, HEIGHT = display.get_bounds() # 240x135
BLACK = display.create_pen(0, 0, 0)
WHITE = display.create_pen(255, 255, 255)
BAR_WIDTH = 5
wattValues = []
COLORS_DISP = [(0, 0, 255), (0, 255, 0), (255, 255, 0), (255, 0, 0)]
# fills the screen with black
display.set_pen(BLACK)
display.clear()
display.update()
class RgbControl(object):

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

    def start_pulse(self, valid:bool, color):
        if valid:
            self.color = color
            self.freq = 30
            if not (self.timerIsInitialized):
                self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
                self.timerIsInitialized = True
        else:
            self.color = (240, 0, 0)
            self.freq = 100
            self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
            self.timerIsInitialized = False # always do a fresh init for the error case. Don't check the isInitialized value

    def set_const_color(self, color):
        self.timer_rgb.deinit() # not always needed
        self.timerIsInitialized = False
        self.led_rgb.set_rgb(*color)

# start with h = variable, s = 0.5, v = 0.5, a = LedBrightness/255
def hsva_to_rgb(h:float, s:float, v:float, a:float) -> tuple:    # inputs: values from 0.0 to 1.0. Outputs are integers, range 0 to 255
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

def value_to_rgb(value:int, value_max:int, led_brightness:int, invert:bool)-> list: # goes from red to blue
    if invert:
        value = value_max - value # reverse the value to have a 'blue is good'-meaning
    h = float(value) / float(1.4*value_max) # h value makes a 'circle'. This means 0 degree is the same as 360°. -> Need to limit it (but not to 180°, just less than 360)
    a = float(led_brightness) / float(255)
    return list(hsva_to_rgb(h, 1.0, 1.0, a))

def right_align(value):
    if value < 10:
        return "   "
    if value < 100:
        return "  "
    if value < 1000:
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

def getBrightness(meas:list):
    brightness = meas["brightness"]
    if (meas["hour"] > 20) or (meas["hour"] < 6):
        brightness = round(0.25 * meas["brightness"]) # darker from 21:00 to 05:59
    return brightness

rgb_control = RgbControl()
rgb_control.set_const_color((255,0,0))

while True:
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('userid', device_config['userid']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    meas = send_message_get_response(DBGCFG=DBGCFG, message=message) # does not send anything when in simulation
    
    
    wattValueNonMaxed = (-1 * meas["wattCons"]) + meas["wattGen"] # cons is negative, gen positive
    if wattValueNonMaxed < 0: # if both are the same, it's treated as gen
        generating = 0
    else:
        generating = 1

    ledMaxVal = meas["max"]
    if generating == 1:
        ledMaxVal = meas["maxGen"]

    # normalize the value between 0 and max
    wattValueNormalized = abs(wattValueNonMaxed)
    wattValueNormalized = min(wattValueNormalized, ledMaxVal)    

    debug_print(DBGCFG, "normalized watt value: "+str(wattValueNormalized)+", generating: "+str(generating)+", max/bright: "+str(ledMaxVal)+"/"+str(meas["brightness"]))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattValues.append(wattValueNormalized)
    if len(wattValues) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValues.pop(0)

    i = 0
    for t in wattValues:
        colorVal = t
        color_pen = display.create_pen(*value_to_rgb(value=colorVal, value_max=ledMaxVal, led_brightness=255, invert=(generating==0)))
        display.set_pen(color_pen)
        display.rectangle(i, int(HEIGHT - (float(t) / float(ledMaxVal / HEIGHT))), BAR_WIDTH, HEIGHT)
        i += BAR_WIDTH

    display.set_pen(WHITE)
    display.rectangle(1, 1, 137, 41) # draws a white background for the text
    wattValueNonMaxed = min(wattValueNonMaxed, 9999) # limit it to 4 digits
    expand = right_align(wattValueNonMaxed) # string formatting does not work correctly. Do it myself

    # writes the reading as text in the white rectangle
    display.set_pen(BLACK)
    make_bold(display, expand+str(wattValueNonMaxed), 7, 23) # str.format does not work as intended
    make_bold(display, "W", 104, 23)
    
    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    if (meas["valid"] == 0):
        rgb_control.start_pulse(valid=False, color=(0,0,0)) # pulsate red with high brightness
    else:
        brightness = getBrightness(meas=meas)
        if (generating == 1):            
            rgb_control.start_pulse(valid=True, color=value_to_rgb(value=wattValueNormalized, 
                                                                   value_max=ledMaxVal,
                                                                   led_brightness=brightness,
                                                                   invert=False))
        else:
            rgb_control.set_const_color(color=value_to_rgb(value=wattValueNormalized, 
                                                           value_max=ledMaxVal,
                                                           led_brightness=int(brightness/2), # led is quite bright when shining constantly
                                                           invert=True))
        
    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
