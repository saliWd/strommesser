## xx_version_placeholder_xx

import gc
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2, PEN_RGB565  # type: ignore
from picovector import PicoVector, ANTIALIAS_X16, Polygon # type: ignore See https://github.com/pimoroni/presto/blob/main/docs/picovector.md
from time import time, sleep
import machine # type: ignore 
from pngdec import PNG # type: ignore
from micropython import const # type: ignore

# my own files
from class_def import RgbLed # class def
from function_def import val_to_rgb, getDispYrange, json_get_req, tx_to_server, feed_wdt, wlan_init, getBrightness, do_ota
import my_config

DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
USE_WDT:bool = DEBUG_CFG['use_watchdog']

DEVICE_CFG = my_config.get_device_config()
WLAN_CFG = my_config.get_wlan_config()
LOOP_SLEEP_SEC = const(4) # pause between loops. Results in about 5 seconds loop
WATT_NOISE_LIMIT = const(15) # everything below 15 W will be set to 0
TRANSMIT_EVERY_X_SECONDS = const(120)
otaCheckAfterXseconds = 180 # first check after 3 mins, will be extended to 24h after the first check

display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2, rotate=0, pen_type=PEN_RGB565)
display.set_backlight(0.8)
display.set_font('bitmap8') # for the non-fancy text output during startup

vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_X16)
# font from https://github.com/Gadgetoid/alright-fonts/blob/effb2fca35909a0f2aff7ed04b76c14286490817/sample-fonts/OpenSans/OpenSans-SemiBold.af, stored in root on filesystem. 
vector.set_font('font.af', 30)

WIDTH, HEIGHT, BAR_HEIGHT = const(320), const(240), const(180) # want some empty space on top/bottom, bar is thus smaller than 240
BLACK       = display.create_pen(0, 0, 0)
WHITE       = display.create_pen(255, 255, 255)
COLOR_PLUS  = display.create_pen(170, 255, 170)
COLOR_MINUS = display.create_pen(255, 170, 170)
BAR_WIDTH = const(5)
wattValsNorm = []
wattValsNonNorm = []


rgb_led = RgbLed()
rgb_led.control(allOk=False, pulsating=False, color=[255,0,0])

display.set_pen(BLACK)
display.clear()
display.set_pen(WHITE)
display.text('...verbinde mit WLAN...', 10, 10, scale=1)
display.text(WLAN_CFG['ssid'], 10, 25, scale=1)
display.update()

wlan = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG) # may take some time

# fills the screen
png = PNG(display)
png.open_file('background.png')
png.decode(0, 0)
display.update()

# start the watchdog after wlan_init (which may take longer and does a reboot if not successful)
if USE_WDT: wdt = machine.WDT(timeout=8388) # max time, 8.3 sec
else: wdt = 0

loopCount:int = 0
timeAtLastTransmit = time() # returns seconds
timeAtLastOtaCheck = time()
settings = dict([
    ('serverOk', 1),
    ('brightness', 33),
    ('minValCon', 400),
    ('maxValGen', 3400),
    ('earn', 0.0)
])
measErrorCnt:int = 0
feed_wdt(useWdt=USE_WDT,wdt=wdt)

while True:
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    loopCount += 1 # just let it overflow

    ## do it once, shortly (3 mins) after booting, then don't do it for about 24 hours
    if ((time() - timeAtLastOtaCheck) > otaCheckAfterXseconds):
        timeAtLastOtaCheck = time() # reset the counter
        otaCheckAfterXseconds = 86400 # 24h
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        do_ota(DEBUG_CFG) # maybe reboots, maybe not
        continue

    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    meas = json_get_req(DEBUG_CFG=DEBUG_CFG, local_ip=DEVICE_CFG['local_ip'])
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    if meas['valid']:
        measErrorCnt = 0
    else:
        measErrorCnt += 1
        print('get request did not work')
        if measErrorCnt > 5:
            print('sleeping for 10 seconds')
            sleep(10) # this will trigger the watchdog and force a reboot
        sleep(2)
        continue

    wattVal = int(1000.0 * (-1.0*meas['power_pos'] + meas['power_neg'])) # cons is negative, gen positive. 0 is treated as gen
    # print('wattValue: '+str(wattVal)) # debug
    
    if (abs(wattVal) < WATT_NOISE_LIMIT): # everything below this is just noise...
        wattVal = 0
    # print('wattValue: '+str(wattVal))
    
    minValCon = int(settings['minValCon']) # this is a positive value but needs to be treated negative in some cases
    maxValGen = int(settings['maxValGen'])

    # normalize the value between -ledMinValCon and ledMaxValGen (e.g. -400 to 3000)
    wattValMinMax = min(max(wattVal, (-1 * minValCon)),maxValGen)
    #print('normalized watt value: '+str(wattValMinMax)+', min/max: '+str(minValCon)+'/'+str(maxValGen))

    png.decode(0, 0)
    feed_wdt(useWdt=USE_WDT,wdt=wdt)

    wattValsNorm.append(wattValMinMax)
    wattValsNonNorm.append(wattVal) # the non-normalized value
    if len(wattValsNorm) > WIDTH // BAR_WIDTH: # shifts the wattValues history to the left by one sample
        wattValsNorm.pop(0)
        wattValsNonNorm.pop(0)
    valColor = val_to_rgb(val=wattValMinMax, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255)
    # draw the zero line in the current color (1 pix)
    display.set_pen(display.create_pen(*valColor))
    disp_y_range = getDispYrange(wattValsNonNorm)
    zeroLine_y = HEIGHT - int(float(HEIGHT) * float(disp_y_range[0]) / float(disp_y_range[2]))
    display.rectangle(0, zeroLine_y, WIDTH, 1)

    x = 0
    color_pen = WHITE
    valHeight = 0
    wattValNorm, wattValNonNorm = 0,0
    length = len(wattValsNorm) # length of both arrays are equal
    for i in range(length):
        wattValNorm = wattValsNorm[i] # this is used for the color
        wattValNonNorm = wattValsNonNorm[i] # this is used for the size

        # cons grow down (so plus direction in pixels), gen grow up (so need to 'invert' everything). Full range is (min+max Vals)
        color_pen = display.create_pen(*val_to_rgb(val=wattValNorm, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255))
        display.set_pen(color_pen)
        
        valHeight = int(float(BAR_HEIGHT) * float(abs(wattValNonNorm)) / float(disp_y_range[2])) # between 0 and BAR_HEIGHT. E.g. 135*2827/3400
        if wattValNonNorm < 0: 
            display.rectangle(x, zeroLine_y, BAR_WIDTH, valHeight)
        else: # direction goes up
            display.rectangle(x, zeroLine_y-valHeight, BAR_WIDTH, valHeight)
        x += BAR_WIDTH
    feed_wdt(useWdt=USE_WDT,wdt=wdt)

    if wattVal < 0: display.set_pen(COLOR_MINUS)
    else:           display.set_pen(COLOR_PLUS)
    wattVal4digits = min(abs(wattVal), 9999) # limit it to 4 digits, range 0...9999. Sign is lost

    # writes the reading as text in the rectangle
    txtNum = str(wattVal4digits)
    x, y, w, h = vector.measure_text(txtNum, x=44, y=32, angle=None)
    w = int(w)

    wOutline = Polygon()
    wOutline.rectangle(6,6,127,36, corners=(2, 2, 2, 2), stroke=2)
    vector.draw(wOutline)
    display.set_pen(WHITE)
    vector.text('W', 102, 32, 0)
    vector.text(txtNum, 92-w, 31, 0)
    
    earnTxt = '{0:.2f}'.format(settings['earn'])
    x, y, w, h = vector.measure_text(earnTxt, x=196, y=227, angle=None)
    w = int(w)

    display.set_pen(WHITE)
    vector.text('CHF',201,227,0)
    vector.text(earnTxt,306-w,225,0)

    vector.text(str(loopCount), 10, 227, 0)
    
    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    brightness, pulsed = getBrightness(setting=int(settings['brightness']), time=meas['date_time'], wattVal=wattVal) # dependency on time
    # print('brightness output: wattVal:settings:applied'+str(wattVal)+':'+str(settings['brightness'])+':'+str(brightness))
    
    rgb_led.control(
        allOk=bool(settings['serverOk']), # NB: meas['valid'] is True. Otherwise we break the loop
        pulsating=pulsed,
        color=val_to_rgb(
            val=wattValMinMax,
            minValCon=minValCon,
            maxValGen=maxValGen,
            led_brightness=int(brightness/2))) # led is quite bright when shining constantly
    
    if ((time() - timeAtLastTransmit) > TRANSMIT_EVERY_X_SECONDS):
        timeAtLastTransmit = time() # reset the counter
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        settings = tx_to_server(DEBUG_CFG=DEBUG_CFG, DEVICE_CFG=DEVICE_CFG, meas=meas, loopCount=loopCount,
                                useWdt=USE_WDT, wdt=wdt) # now transmit the stuff to the server
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
    
    try:
        del x,y,w,h,wattValNorm,meas,wattVal,minValCon,maxValGen,wattValMinMax,valColor,disp_y_range,length,wattValNonNorm
        del zeroLine_y,color_pen,valHeight,wattVal4digits,txtNum,wOutline,earnTxt,brightness,pulsed # to combat memAlloc issues
    except Exception as error:
        print("An exception occurred:", error)
    gc.collect() # garbage collection
    
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    sleep(LOOP_SLEEP_SEC)
