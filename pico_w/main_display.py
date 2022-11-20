import network # type: ignore (this is a pylance ignore warning directive)
import urequests # type: ignore
from time import sleep
from machine import Timer, WDT # type: ignore
from pimoroni import RGBLED  # type: ignore
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore

# my own files
import my_config
from my_functions import debug_wdtFeed, debug_print, debug_sleep, wlan_connect, urlencode, get_randNum_hash


def send_message_get_response(wdt, DBGCFG:dict, message:dict):
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    if (DBGCFG["wlan_sim"]):
        return("1|57") # valid|57W
    
    URL = "https://strommesser.ch/verbrauch/getRaw.php?TX=pico&TXVER=2"
    HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}
    urlenc = urlencode(message)
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG) # before the urequest (has a timeout of 30s, so wdt will trigger)
    response = urequests.post(URL, data=urlenc, headers=HEADERS)
    debug_print(DBGCFG, response.text)
    returnText = response.text
    response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    return(returnText)

DBGCFG = my_config.get_debug_settings() # debug stuff
LOOP_WAIT_TIME = 60
if DBGCFG["wdt_dis"]:
    wdt = 0
else:
    wdt = WDT(timeout=8300)  # enable it with a timeout of 8s. NB: maximum timeout is 8.388 seconds
debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
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
VALUE_MAX = 3 * HEIGHT # 405
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

    def pulse_green_cb(self, noIdeaWhyThisIsNeeded):
        if self.tick:
            self.led_rgb.set_rgb(*(0, 0, 0))
        else:
            self.led_rgb.set_rgb(*(0, 127, 0))
        self.tick = not(self.tick)    

    def start_pulse(self, green:bool):
        if green:
            self.timer_rgb.init(freq=2, callback=self.pulse_green_cb)
        else:
            self.timer_rgb.init(freq=2, callback=self.pulse_red_cb)

    def stop_pulse(self):
        self.timer_rgb.deinit()
        self.led_rgb.set_rgb(*(0, 0, 0))

    def set_const_color(self, color):
        self.timer_rgb.deinit() # not always needed
        self.led_rgb.set_rgb(*color)


def value_to_color(value, disp:bool): # value must be between 0 and VALUE_MAX
    if disp:
        colors = COLORS_DISP
    else:
        colors = COLORS_LED
    f_index = float(value) / float(VALUE_MAX)
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

rgb_control = RgbControl()
rgb_control.start_pulse(green=False) # signal startup

while True:
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('device', device_config['device_name']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(wdt=wdt, DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    wattValueString = send_message_get_response(wdt=wdt, DBGCFG=DBGCFG, message=message) # does not send anything when in simulation

    validValue = wattValueString.split("|")
    if (len(validValue) != 2 ):
        valid = 0
        wattValue = 999
    else:
        valid = int(validValue[0])
        wattValue = int(validValue[1])
    
    # normalize the value
    wattValueNonMaxed = wattValue
    wattValue = min(wattValue, VALUE_MAX)
    wattValue = max(wattValue, 0)

    debug_print(DBGCFG, "watt value: "+str(wattValue))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattValues.append(wattValue)
    if len(wattValues) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValues.pop(0)

    i = 0
    for t in wattValues:        
        VALUE_COLOUR = display.create_pen(*value_to_color(t,disp=True))
        display.set_pen(VALUE_COLOUR)
        display.rectangle(i, int(HEIGHT - (float(t) / 3.0)), BAR_WIDTH, HEIGHT) # TODO: height-t needs to match with min/max scaling
        i += BAR_WIDTH

    display.set_pen(WHITE)
    display.rectangle(1, 1, 137, 41) # draws a white background for the text
    debug_wdtFeed(wdt=wdt, DBGCFG=DBGCFG)
    expand = right_align(wattValueNonMaxed) # string formatting does not work correctly. Do it myself
    wattValueNonMaxed = min(wattValueNonMaxed, 9999) # limit it to 4 digits

    # writes the reading as text in the white rectangle
    display.set_pen(BLACK)
    make_bold(display, expand+str(wattValueNonMaxed), 7, 23) # str.format does not work as intended
    make_bold(display, "W", 104, 23) 
    
    display.update()

    # lets also set the LED to match
    if (valid == 0):
        rgb_control.start_pulse(green=False) # pulsate red
    else:
        rgb_control.set_const_color(value_to_color(wattValue,disp=False))
        if (wattValueNonMaxed == 0):
            rgb_control.start_pulse(green=True)
    
    debug_sleep(wdt, DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
    