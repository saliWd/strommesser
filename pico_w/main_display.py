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
    SIM_STR = "1|57|2023|01|27|18|22|09|500|100|727"
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

    def start_pulse(self, valid, color):
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


def value_to_color(value, colors:list, value_max:int): # value must be between 0 and value_max
    f_index = float(value) / float(value_max)
    f_index *= len(colors) - 1
    index = int(f_index)

    if index == len(colors) - 1:
        return colors[index]

    blend_b = f_index - index
    blend_a = 1.0 - blend_b

    a = colors[index]
    b = colors[index + 1]

    return [int((a[i] * blend_a) + (b[i] * blend_b)) for i in range(3)]

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
previousGenerating = 0
generating = 0

while True:
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('userid', device_config['userid']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    meas = send_message_get_response(DBGCFG=DBGCFG, message=message) # does not send anything when in simulation
    
    previousGenerating = generating
    generating = 0
    wattValueNonMaxed = abs(meas["wattCons"] - meas["wattGen"]) # want this value always positive
    if meas["wattGen"] > meas["wattCons"]: # if both are the same, it's consumption
        generating = 1
        
    if (generating != previousGenerating): # if the value changes from generated to consumed (or vice versa): erase the screen because it does not make sense anymore
        wattValues.clear() 

    ledMaxVal = meas["max"]
    if generating == 1:
        ledMaxVal = meas["maxGen"]

    # normalize the value. Is between 0 and max
    wattValueNormalized = wattValueNonMaxed
    wattValueNormalized = min(wattValueNormalized, ledMaxVal)    

    debug_print(DBGCFG, "normalized watt value: "+str(wattValueNormalized)+", max/bright: "+str(ledMaxVal)+"/"+str(meas["brightness"]))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattValues.append(wattValueNormalized)
    if len(wattValues) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValues.pop(0)

    i = 0
    for t in wattValues:
        colourVal = t
        if generating == 1:
            colourVal = ledMaxVal - colourVal # reverse the value to have a 'blue is good'-meaning
        
        VALUE_COLOUR = display.create_pen(*value_to_color(value=colourVal,colors=COLORS_DISP,value_max=ledMaxVal))
        display.set_pen(VALUE_COLOUR)
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
        rgb_control.start_pulse(False, (0,0,0)) # pulsate red with high brightness
    else:
        brightness = getBrightness(meas=meas)
        COLORS_LED = [(0, 0, brightness), (0, brightness, 0), (brightness, brightness, 0), (brightness, 0, 0)]
        if (generating == 1):
            wattValueNormalized = ledMaxVal - wattValueNormalized # reverse the value to have a 'blue is good'-meaning
            rgb_control.start_pulse(True, value_to_color(value=wattValueNormalized,colors=COLORS_LED,value_max=ledMaxVal))
        else:
            rgb_control.set_const_color(value_to_color(value=wattValueNormalized,colors=COLORS_LED,value_max=ledMaxVal))
        
    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)

