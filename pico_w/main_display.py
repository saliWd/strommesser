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

def getBrightness(meas:list):
    brightness = meas["brightness"]
    if (meas["hour"] > 20) or (meas["hour"] < 6):
        brightness = round(0.25 * meas["brightness"]) # darker from 21:00 to 05:59
    return brightness

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
TEXT_BG_GEN = display.create_pen(0, 255, 0) # TODO: work on those colors
TEXT_BG_CONS = display.create_pen(255, 0, 0)
TEXT_GEN = display.create_pen(255, 0, 0)
TEXT_CONS = display.create_pen(0, 255, 0)
BAR_WIDTH = 5
wattVals = []
# fills the screen with black
display.set_pen(BLACK)
display.clear()
display.update()
rgb_led = RgbLed()
rgb_led.control(valid=False, pulsating=False, color=(255,0,0))

while True:
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('userid', device_config['userid']),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
        
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    meas = send_message_get_response(DBGCFG=DBGCFG, message=message) # does not send anything when in simulation
    
    
    wattVal = (-1 * meas["wattCons"]) + meas["wattGen"] # cons is negative, gen positive
    
    minValCons = meas["max"] # this is a positive value but needs to be treated negative in some cases
    maxValGen = meas["maxGen"]

    # normalize the value between -ledMinValCons and ledMaxValGen (e.g. -400 to 3000)
    wattValMinMax = min(max(wattVal, (-1 * minValCons)),maxValGen)

    debug_print(DBGCFG, "normalized watt value: "+str(wattValMinMax)+
                ", min/max/bright: "+str(minValCons)+"/"+str(maxValGen)+"/"+str(meas["brightness"]))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattVals.append(wattValMinMax)
    if len(wattVals) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattVals.pop(0)
    valColor = val_to_rgb(val=wattValMinMax, minValCons=minValCons, maxValGen=maxValGen, led_brightness=255)
    # draw the zero line in the current color (1 pix)
    display.set_pen(display.create_pen(*valColor))
    zeroLine_y = HEIGHT - int(float(HEIGHT) * float(minValCons) / float(minValCons+maxValGen))    
    display.rectangle(0, zeroLine_y, WIDTH, 1)

    # Debug code, to get both cons and gen
    # if len(wattVals) == 12: wattVals[7] = wattVals[7]*-1

    x = 0
    for t in wattVals:
        # cons grow down (so plus direction in pixels), gen grow up (so need to 'invert' everything). Full range is (min+max Vals)
        color_pen = display.create_pen(*val_to_rgb(val=t, minValCons=minValCons, maxValGen=maxValGen, led_brightness=255))        
        display.set_pen(color_pen)
        
        valHeight = int(float(HEIGHT) * float(abs(t)) / float(minValCons+maxValGen)) # between 0 and HEIGHT. E.g. 135*2827/3400
        if t < 0: 
            display.rectangle(x, zeroLine_y, BAR_WIDTH, valHeight)
        else: # direction goes up
            display.rectangle(x, zeroLine_y-valHeight, BAR_WIDTH, valHeight)        
        x += BAR_WIDTH

    if wattVal > 0: display.set_pen(TEXT_BG_CONS)
    else:           display.set_pen(TEXT_BG_GEN)
    display.rectangle(1, 1, 137, 41) # draws a background for the black text
    wattVal4digits = min(abs(wattVal), 9999) # limit it to 4 digits, range 0...9999. Sign is lost
    expand = right_align(value4digits=wattVal4digits) # string formatting does not work correctly. Do it myself

    # writes the reading as text in the rectangle
    if wattVal > 0: display.set_pen(TEXT_CONS)
    else:           display.set_pen(TEXT_GEN)
    
    make_bold(display, expand+str(wattVal4digits), 7, 23) # str.format does not work as intended
    make_bold(display, "W", 104, 23)
    
    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    brightness = getBrightness(meas=meas)
    if (wattVal > 0):
        rgb_led.control(valid=(meas["valid"] == 1), pulsating=True,
                        color=val_to_rgb(val=wattValMinMax, 
                                         minValCons=minValCons, 
                                         maxValGen=maxValGen, 
                                         led_brightness=brightness))
    else:
        rgb_led.control(valid=(meas["valid"] == 1), pulsating=False,
                        color=val_to_rgb(val=wattValMinMax,
                                         minValCons=minValCons, 
                                         maxValGen=maxValGen, 
                                         led_brightness=int(brightness/2))) # led is quite bright when shining constantly
    
    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
