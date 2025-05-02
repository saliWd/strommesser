# using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# does not work: Micropython version Raspberry Pico 2 W with Pimoroni libraries 0.0.11
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

import gc
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore
from time import time

# my own files
from class_def import RgbLed # class def
from function_def import val_to_rgb, right_align, make_bold, getDispYrange, json_get_req, tx_to_server, debug_sleep, wlan_init, wlan_conn_check, print_loopCount, getBrightness
import my_config

DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
DEVICE_CFG = my_config.get_device_config()
WLAN_CFG = my_config.get_wlan_config()
LOOP_SLEEP_SEC = 5 # pause between loops
WATT_NOISE_LIMIT = 15 # everything below 15 W will be set to 0
TRANSMIT_EVERY_X_SECONDS = 120


wlan = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG)

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
rgb_led.control(allOk=False, pulsating=False, color=(255,0,0))

loopCount:int = 0
timeSinceLastTransmit = time() # returns seconds

READER = 'gplug' # can be gplug or whatwatt
settings = dict([
    ('valid',True),
    ('serverOk', 1),
    ('brightness', 33),
    ('minValCon', 400),
    ('maxValGen', 3400)
])

while True:
    loopCount += 1 # just let it overflow
    wlan = wlan_conn_check(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG, wlan=wlan) # check whether connection is still valid
    meas = json_get_req(DEBUG_CFG=DEBUG_CFG, DEVICE_CFG=DEVICE_CFG, READER=READER)
    if not meas['valid']:
        print('get request did not work')
        continue

    wattVal = int(1000.0 * (-1.0*meas['power_pos'] + meas['power_neg'])) # cons is negative, gen positive. 0 is treated as gen
    
    if (abs(wattVal) < WATT_NOISE_LIMIT): # everything below this is just noise...
        wattVal = 0
    #print('wattValue: '+str(wattVal))
    
    minValCon = settings['minValCon'] # this is a positive value but needs to be treated negative in some cases
    maxValGen = settings['maxValGen']

    # normalize the value between -ledMinValCon and ledMaxValGen (e.g. -400 to 3000)
    wattValMinMax = min(max(wattVal, (-1 * minValCon)),maxValGen)
    #print("normalized watt value: "+str(wattValMinMax)+", min/max: "+str(minValCon)+"/"+str(maxValGen))

    # fills the screen with black
    display.set_pen(BLACK)
    display.clear()

    wattVals.append(wattValMinMax)
    if len(wattVals) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattVals.pop(0)
    valColor = val_to_rgb(val=wattValMinMax, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255)
    # draw the zero line in the current color (1 pix)
    display.set_pen(display.create_pen(*valColor))
    disp_y_range = getDispYrange(wattVals)
    zeroLine_y = HEIGHT - int(float(HEIGHT) * float(disp_y_range[0]) / float(disp_y_range[2]))
    display.rectangle(0, zeroLine_y, WIDTH, 1)

    # Debug code, to get both cons and gen
    # if len(wattVals) == 12: wattVals[7] = wattVals[7]*-1

    x = 0
    for t in wattVals:
        # cons grow down (so plus direction in pixels), gen grow up (so need to 'invert' everything). Full range is (min+max Vals)
        color_pen = display.create_pen(*val_to_rgb(val=t, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255))
        display.set_pen(color_pen)
        
        valHeight = int(float(HEIGHT) * float(abs(t)) / float(disp_y_range[2])) # between 0 and HEIGHT. E.g. 135*2827/3400
        if t < 0: 
            display.rectangle(x, zeroLine_y, BAR_WIDTH, valHeight)
        else: # direction goes up
            display.rectangle(x, zeroLine_y-valHeight, BAR_WIDTH, valHeight)
        x += BAR_WIDTH

    if wattVal < 0: display.set_pen(TEXT_BG_CONS)
    else:           display.set_pen(TEXT_BG_GEN)
    display.rectangle(1, 1, 137, 41) # draws a background for the black text
    wattVal4digits = min(abs(wattVal), 9999) # limit it to 4 digits, range 0...9999. Sign is lost
    expand = right_align(value4digits=wattVal4digits) # string formatting does not work correctly. Do it myself

    # writes the reading as text in the rectangle
    display.set_pen(BLACK)
    make_bold(display, expand+str(wattVal4digits), 7, 23) # str.format does not work as intended
    make_bold(display, "W", 104, 23)
    
    print_loopCount(display=display, BLACK=BLACK, loopCount=str(loopCount))    

    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    brightness = getBrightness(setting=settings['brightness'], time=meas['date_time']) # dependency on time
    #print('brightness: settings:applied'+str(settings['brightness'])+':'+str(brightness))
    allOk = meas['valid'] and settings['serverOk']
    if (wattVal == 0): brightness = 0 # disable LED when 0 consumption
    if (wattVal < 0):
        rgb_led.control(allOk=allOk, pulsating=False,
                        color=val_to_rgb(val=wattValMinMax,
                                         minValCon=minValCon, 
                                         maxValGen=maxValGen, 
                                         led_brightness=int(brightness/2))) # led is quite bright when shining constantly
    else:
        rgb_led.control(allOk=allOk, pulsating=True,
                        color=val_to_rgb(val=wattValMinMax, 
                                         minValCon=minValCon, 
                                         maxValGen=maxValGen, 
                                         led_brightness=brightness))

    if ((time() - timeSinceLastTransmit) > TRANSMIT_EVERY_X_SECONDS):
        timeSinceLastTransmit = time() # reset the counter        
        settings = tx_to_server(DEBUG_CFG=DEBUG_CFG, DEVICE_CFG=DEVICE_CFG, meas=meas, settings=settings) # now transmit the stuff to the server


    # do not delete wlan variable and timeSinceLastTransmit
    del brightness, color_pen, disp_y_range, expand, minValCon, maxValGen, meas, t
    del valColor, valHeight, wattVal, wattVal4digits, wattValMinMax, x, zeroLine_y  # to combat memAlloc issues
    gc.collect() # garbage collection
    
    debug_sleep(DEBUG_CFG=DEBUG_CFG,time=LOOP_SLEEP_SEC)
