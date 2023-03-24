import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
from machine import Timer # type: ignore
from pimoroni import RGBLED  # type: ignore
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore

# my own files
import my_config
from my_functions import debug_print, debug_sleep, wlan_connect, get_randNum_hash, transmit_message

def SecondCoreTask(): # reboots after about ~1h
    reset_counter = 40 # do a regular reboot (stability increase work around)
    while True:
        sleep(120) # seconds
        if reset_counter > 0:
            reset_counter -= 1
        else:
            from machine import reset # type: ignore
            reset() # NB: connection to whatever device is getting lost; complicates debugging

def send_message_get_response(DBGCFG:dict, message:dict):    
    URL = "https://strommesser.ch/verbrauch/getRaw.php?TX=pico&TXVER=2"
    SIM_STR = "1|57|2023|01|27|18|22|09|500|100"
    if (DBGCFG["wlan_sim"]):        
        return(sepStrToArr(separatedString=SIM_STR))            
    
    returnText = transmit_message(DBGCFG=DBGCFG, URL=URL, message=message)    
    return(sepStrToArr(separatedString=returnText))

DBGCFG = my_config.get_debug_settings() # debug stuff
LOOP_WAIT_TIME = 80
wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(1)

tim_rgb = Timer() # no need to specify a number on pico, all SW timers       

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
        self.tick = True
        self.led_rgb = RGBLED(6, 7, 8)
        self.timer_rgb = Timer()

    def pulse_red_cb(self, noIdeaWhyThisIsNeeded):
        if self.tick:
            self.led_rgb.set_rgb(*(0, 0, 0))
        else:
            self.led_rgb.set_rgb(*(255, 0, 0))
        self.tick = not(self.tick)

    def pulse_blue_cb(self, noIdeaWhyThisIsNeeded):
        if self.tick:
            self.led_rgb.set_rgb(*(0, 0, 0))
        else:
            self.led_rgb.set_rgb(*(0, 0, 127))
        self.tick = not(self.tick)    

    def start_pulse(self, blue:bool):
        if blue:
            self.timer_rgb.init(freq=2, callback=self.pulse_blue_cb)
        else:
            self.timer_rgb.init(freq=2, callback=self.pulse_red_cb)

    def stop_pulse(self):
        self.timer_rgb.deinit()
        self.led_rgb.set_rgb(*(0, 0, 0))

    def set_const_color(self, color):
        self.timer_rgb.deinit() # not always needed
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

def getBrightness(meas:list):
    brightness = meas["brightness"]
    if (meas["hour"] > 21) or (meas["hour"] < 6):
        brightness = round(0.5 * meas["brightness"]) # darker from 22:00 to 05:59
    return brightness

rgb_control = RgbControl()
rgb_control.start_pulse(blue=False) # signal startup

second_core_idle = True

while True:
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('userid', device_config['userid']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    meas = send_message_get_response(DBGCFG=DBGCFG, message=message) # does not send anything when in simulation
    
    # normalize the value. Is between 0 and max
    wattValueNonMaxed = meas["wattValue"]    
    meas["wattValue"] = min(meas["wattValue"], meas["max"])
    meas["wattValue"] = max(meas["wattValue"], 0)

    debug_print(DBGCFG, "normalized watt value: "+str(meas["wattValue"])+", max/bright: "+str(meas["max"])+"/"+str(meas["brightness"]))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattValues.append(meas["wattValue"])
    if len(wattValues) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValues.pop(0)

    i = 0
    for t in wattValues:        
        VALUE_COLOUR = display.create_pen(*value_to_color(value=t,colors=COLORS_DISP,value_max=meas["max"]))
        display.set_pen(VALUE_COLOUR)
        display.rectangle(i, int(HEIGHT - (float(t) / float(meas["max"] / HEIGHT))), BAR_WIDTH, HEIGHT)
        i += BAR_WIDTH

    display.set_pen(WHITE)
    display.rectangle(1, 1, 137, 41) # draws a white background for the text
    expand = right_align(wattValueNonMaxed) # string formatting does not work correctly. Do it myself
    wattValueNonMaxed = min(wattValueNonMaxed, 9999) # limit it to 4 digits

    # writes the reading as text in the white rectangle
    display.set_pen(BLACK)
    make_bold(display, expand+str(wattValueNonMaxed), 7, 23) # str.format does not work as intended
    make_bold(display, "W", 104, 23)
    
    display.update()

    # lets also set the LED to match
    if (meas["valid"] == 0):
        rgb_control.start_pulse(blue=False) # pulsate red
    else:
        brightness = getBrightness(meas=meas)
        COLORS_LED = [(0, 0, brightness), (0, brightness, 0), (brightness, brightness, 0), (brightness, 0, 0)]
        rgb_control.set_const_color(value_to_color(value=meas["wattValue"],colors=COLORS_LED,value_max=meas["max"]))
        if (wattValueNonMaxed == 0):
            rgb_control.start_pulse(blue=True)
    
    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
    