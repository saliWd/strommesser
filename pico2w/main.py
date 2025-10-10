## xx_version_placeholder_xx

import micropython_ota # type: ignore | using version 2.1.0., install with thonny/tools/packages
import gc
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2, PEN_RGB565  # type: ignore
from time import time
import machine # type: ignore 

from pngdec import PNG # type: ignore

# my own files
from class_def import RgbLed # class def
from function_def import val_to_rgb, right_align, getDispYrange, make_bold, json_get_req, tx_to_server, feed_wdt, debug_sleep, wlan_init, wlan_conn_check, getBrightness
import my_config


DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
USE_WDT:bool = DEBUG_CFG['use_watchdog']

DEVICE_CFG = my_config.get_device_config()
WLAN_CFG = my_config.get_wlan_config()
LOOP_SLEEP_SEC = 3 # pause between loops. Results in about 5 seconds loop
WATT_NOISE_LIMIT = 15 # everything below 15 W will be set to 0
TRANSMIT_EVERY_X_SECONDS = 120
otaCheckAfterXseconds = 180 # first check after 3 mins, will be extended to 24h after the first check

wlan = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG)

# start the watchdog after wlan_init (which may take longer and does a reboot if not successful)
if USE_WDT: wdt = machine.WDT(timeout=8388) # max time, 8.3 sec
else: wdt = 0

display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2, rotate=0, pen_type=PEN_RGB565)
display.set_backlight(0.8)
display.set_font("sans")
TXT_SCALE = 0.8
WIDTH, HEIGHT = display.get_bounds() # 320x240
WHITE = display.create_pen(225, 225, 225)
TEXT_BG_GEN = display.create_pen(170, 255, 170)
TEXT_BG_CON = display.create_pen(255, 170, 170)
BAR_WIDTH = 5
wattVals = []
# fills the screen
png = PNG(display)
png.open_file("pico_background.png") # TODO: add it to ota as well
png.decode(0, 0)
display.update()

rgb_led = RgbLed()
rgb_led.control(allOk=False, pulsating=False, color=[255,0,0])

loopCount:int = 0
timeSinceLastTransmit = time() # returns seconds
timeSinceLastOtaCheck = time()

settings = dict([
    ('valid',True),
    ('serverOk', 1),
    ('brightness', 33),
    ('minValCon', 400),
    ('maxValGen', 3400),
    ('earn', 0.0)
])
feed_wdt(useWdt=USE_WDT,wdt=wdt)

while True:
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    loopCount += 1 # just let it overflow
    wlan = wlan_conn_check(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG, wlan=wlan) # check whether connection is still valid

    ## do it once, shortly (3 mins) after booting, then don't do it for about 24 hours
    if ((time() - timeSinceLastOtaCheck) > otaCheckAfterXseconds):
        timeSinceLastOtaCheck = time() # reset the counter
        otaCheckAfterXseconds = 86400 # 24h
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        if (DEBUG_CFG['wlan'] == 'real'): # don't do ota otherwise
            micropython_ota.ota_update(
                host='https://strommesser.ch/ota/',
                project='display',
                filenames=['boot.py', 'main.py', 'function_def.py', 'class_def.py'], # config (and libraries) is not changed
                use_version_prefix=False
            )

    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    meas = json_get_req(DEBUG_CFG=DEBUG_CFG, DEVICE_CFG=DEVICE_CFG)
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    if not meas['valid']:
        print('get request did not work, waiting 10s')
        debug_sleep(DEBUG_CFG=DEBUG_CFG,time=10) # this will trigger the watchdog and force a reboot
        continue

    wattVal = int(1000.0 * (-1.0*meas['power_pos'] + meas['power_neg'])) # cons is negative, gen positive. 0 is treated as gen
    
    if (abs(wattVal) < WATT_NOISE_LIMIT): # everything below this is just noise...
        wattVal = 0
    #print('wattValue: '+str(wattVal))
    
    minValCon = int(settings['minValCon']) # this is a positive value but needs to be treated negative in some cases
    maxValGen = int(settings['maxValGen'])

    # normalize the value between -ledMinValCon and ledMaxValGen (e.g. -400 to 3000)
    wattValMinMax = min(max(wattVal, (-1 * minValCon)),maxValGen)
    #print("normalized watt value: "+str(wattValMinMax)+", min/max: "+str(minValCon)+"/"+str(maxValGen))

    png.decode(0, 0)

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
    color_pen = WHITE
    valHeight = 0
    t = 0
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

#    if wattVal < 0: display.set_pen(TEXT_BG_CON)
#    else:           display.set_pen(TEXT_BG_GEN)
#    display.rectangle(1, 1, 137, 41) # draws a background for the black text
    wattVal4digits = min(abs(wattVal), 9999) # limit it to 4 digits, range 0...9999. Sign is lost
    expand = right_align(value4digits=wattVal4digits) # string formatting does not work correctly. Do it myself
#    display.set_thickness(2)

    # writes the reading as text in the rectangle
    display.set_pen(WHITE)
    make_bold(display, expand+str(wattVal4digits), 12, 23, scale=TXT_SCALE) # str.format does not work as intended
    
    # trial
    earn = settings['earn'] # float value
    earn_str = '{0:.2f}'.format(earn)
#    if earn < 0: display.set_pen(TEXT_BG_CON)
#    else:        display.set_pen(TEXT_BG_GEN)
#    display.rectangle(221, 1, 98, 41) # draws a background for the text
    make_bold(display, earn_str, 205, 220, scale=TXT_SCALE) # max 5 characters: -1.27    
    #print('daily earnings: '+str(earn))
    # /end of trial

    make_bold(display, str(loopCount), 10, 220, scale=TXT_SCALE)

    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    brightness, pulsed = getBrightness(setting=int(settings['brightness']), time=meas['date_time'], wattVal=wattVal) # dependency on time
    # print('brightness output: wattVal:settings:applied'+str(wattVal)+':'+str(settings['brightness'])+':'+str(brightness))
    
    rgb_led.control(
        allOk=((meas['valid']) and (bool(settings['serverOk']) and True)), # need some type conversion (and True) to satisfy pylance
        pulsating=pulsed,
        color=val_to_rgb(
            val=wattValMinMax,
            minValCon=minValCon,
            maxValGen=maxValGen,
            led_brightness=int(brightness/2))) # led is quite bright when shining constantly
    
    if ((time() - timeSinceLastTransmit) > TRANSMIT_EVERY_X_SECONDS):
        timeSinceLastTransmit = time() # reset the counter
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        settings = tx_to_server(DEBUG_CFG=DEBUG_CFG, DEVICE_CFG=DEVICE_CFG, meas=meas, settings=settings, useWdt=USE_WDT, wdt=wdt) # now transmit the stuff to the server
        feed_wdt(useWdt=USE_WDT,wdt=wdt)


    # do not delete wlan variable and timeSinceLastTransmit
    del brightness, pulsed, color_pen, disp_y_range, expand, minValCon, maxValGen, meas, t
    del valColor, valHeight, wattVal, wattVal4digits, wattValMinMax, x, zeroLine_y  # to combat memAlloc issues
    gc.collect() # garbage collection
    
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    debug_sleep(DEBUG_CFG=DEBUG_CFG,time=LOOP_SLEEP_SEC)
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
