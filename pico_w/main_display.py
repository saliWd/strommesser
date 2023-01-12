import network # type: ignore (this is a pylance ignore warning directive)
import urequests # type: ignore
from time import sleep
from machine import Timer # type: ignore
from pimoroni import RGBLED  # type: ignore
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore
import _thread

# my own files
import my_config
from my_functions import debug_print, debug_sleep, wlan_connect, urlencode, get_randNum_hash

def SecondCoreTask(): # reboots after about ~1h
    reset_counter = 40 # do a regular reboot (stability increase work around)
    while True:
        sleep(120) # seconds
        if reset_counter > 0:
            reset_counter -= 1
        else:
            from machine import reset # type: ignore
            reset() # NB: connection to whatever device is getting lost; complicates debugging

def send_message_get_response(DBGCFG:dict, message:dict, isMeasurement:bool):    
    if isMeasurement:
        URL = "https://strommesser.ch/verbrauch/getRaw_v2.php?TX=pico&TXVER=2"
        SIM_STR = "1|57|2023|01|27|18|22|09"
    else:
        URL = "https://strommesser.ch/verbrauch/getConfigDisp_v1.php?TX=pico&TXVER=2"
        SIM_STR = "30|500|100"
    if (DBGCFG["wlan_sim"]):        
        return(sepStrToArr(separatedString=SIM_STR, isMeasurement=isMeasurement))            

    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    urlenc = urlencode(message)
    response = urequests.post(URL, data=urlenc, headers=HEADERS)
    debug_print(DBGCFG, response.text)
    returnText = response.text
    response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
    return(sepStrToArr(separatedString=returnText, isMeasurement=isMeasurement))

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
RGB_BRIGHTNESS = 80 # TODO: adjust this according to the time of the day...
COLORS_LED = [(0, 0, RGB_BRIGHTNESS), (0, RGB_BRIGHTNESS, 0), (RGB_BRIGHTNESS, RGB_BRIGHTNESS, 0), (RGB_BRIGHTNESS, 0, 0)]
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


def value_to_color(value, disp:bool, value_max:int): # value must be between 0 and value_max
    if disp:
        colors = COLORS_DISP
    else:
        colors = COLORS_LED
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

def sepStrToArr(separatedString:str, isMeasurement:bool):
    valueArray = separatedString.split("|") # Format: $valid|$newestConsumption|Y|m|d|H|i|s    
    if isMeasurement:
        retVal = dict([
            ('valid', 0),
            ('wattValue', 999),
            ('hour', 99)
        ])
        if (len(valueArray) > 5 ):
            retVal["valid"] = int(valueArray[0])
            retVal["wattValue"] = int(valueArray[1])
            retVal["hour"] = int(valueArray[5])
    else:
        # I'm assuming some things: min is >= 0, min is smaller than max, max is reasonable (e.g. < 100'000), brightness is between 1 and 255
        retVal = dict([
            ('min', 0),
            ('max', 405),
            ('brightness', 80)
        ])
        if (len(valueArray) > 2 ):
            retVal["min"] = int(valueArray[0])
            retVal["max"] = int(valueArray[1])
            retVal["brightness"] = int(valueArray[2])
    return retVal

rgb_control = RgbControl()
rgb_control.start_pulse(blue=False) # signal startup

second_core_idle = True

while True:
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('device', device_config['device_name']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    measurement = send_message_get_response(DBGCFG=DBGCFG, message=message, isMeasurement=True) # does not send anything when in simulation
    ledConfig = send_message_get_response(DBGCFG=DBGCFG, message=message, isMeasurement=False) # does not send anything when in simulation   
    
    # at two o'clock in the morning (or when receiving invalid data) I start a timer to reset 80mins later
    if (measurement["hour"] == 2) or (measurement["valid"] == 0):
        if second_core_idle: # start it only once
            _thread.start_new_thread(SecondCoreTask, ())
            second_core_idle = False
            debug_print(DBGCFG, "did start the second core")

    # normalize the value. Is between 0 and (max-min)
    wattValueNonMaxed = measurement["wattValue"]
    ledConfig["max"] = ledConfig["max"] - ledConfig["min"]
    measurement["wattValue"] = measurement["wattValue"] - ledConfig["min"]
    measurement["wattValue"] = min(measurement["wattValue"], ledConfig["max"])
    measurement["wattValue"] = max(measurement["wattValue"], 0)

    debug_print(DBGCFG, "normalized watt value: "+str(measurement["wattValue"])+", min/max/bright: "+str(ledConfig["min"])+"/"+str(ledConfig["max"])+"/"+str(ledConfig["brightness"]))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattValues.append(measurement["wattValue"])
    if len(wattValues) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValues.pop(0)

    i = 0
    for t in wattValues:        
        VALUE_COLOUR = display.create_pen(*value_to_color(value=t,disp=True,value_max=ledConfig["max"]))
        display.set_pen(VALUE_COLOUR)
        display.rectangle(i, int(HEIGHT - (float(t) / float(ledConfig["max"] / HEIGHT))), BAR_WIDTH, HEIGHT)
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
    if (measurement["valid"] == 0):
        rgb_control.start_pulse(blue=False) # pulsate red
    else:
        rgb_control.set_const_color(value_to_color(value=measurement["wattValue"],disp=False,value_max=ledConfig["max"]))
        if (wattValueNonMaxed == 0):
            rgb_control.start_pulse(blue=True)
    
    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
    