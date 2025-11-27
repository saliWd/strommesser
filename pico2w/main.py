## xx_version_placeholder_xx

import gc
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2, PEN_RGB565  # type: ignore
from picovector import PicoVector, ANTIALIAS_X16, Polygon # type: ignore See https://github.com/pimoroni/presto/blob/main/docs/picovector.md
from time import time, sleep
import machine # type: ignore Need WDT and reset
from pngdec import PNG # type: ignore
from micropython import const # type: ignore

# my own files
from class_def import RgbLed # class def
from function_def import val_to_rgb, getDispYrange, json_get_req, tx_to_server, feed_wdt, wlan_init, wlan_check, getBrightness, do_ota, runLog
import my_config

logFile = open('run.log', 'a') # append
runLog(file=logFile,string="\nSTAT|main: startup\n")
runLog(file=logFile,string="STAT|main: reset reason (1=power, 3=watchdog) is "+str(machine.reset_cause())+"\n")

DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
USE_WDT:bool = DEBUG_CFG['use_watchdog']

DEVICE_CFG = my_config.get_device_config()
WLAN_CFG = my_config.get_wlan_config()
LOOP_SLEEP_SEC = const(2.3) # 2x this value is the pause between loops. Results in about 5 seconds loop
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

WIDTH, BAR_HEIGHT, MIDDLE = const(320), const(110), const(120) # want some empty space on top/bottom, bar is thus smaller than 120
BLACK       = display.create_pen(0, 0, 0)
WHITE       = display.create_pen(255, 255, 255)
COLOR_PLUS  = display.create_pen(170, 255, 170)
COLOR_MINUS = display.create_pen(255, 170, 170)
BAR_WIDTH = const(5)
wattValsNorm = []
wattValsNonNorm = []

sleep(5) # some wait time to enlarge time between boots

rgb_led = RgbLed()
rgb_led.control(allOk=False, pulsating=False, color=[255,0,0])

display.set_pen(BLACK)
display.clear()
display.set_pen(WHITE)
display.text('...verbinde mit WLAN...', 10, 10, scale=1)
display.text(WLAN_CFG['ssid'], 10, 25, scale=1)
display.update()

if USE_WDT: wdt = machine.WDT(timeout=8388) # max time, 8.3 sec
else: wdt = 0

wlan = wlan_init(DEBUG_CFG=DEBUG_CFG,WLAN_CFG=WLAN_CFG,useWdt=USE_WDT,wdt=wdt,logFile=logFile) # may take some time

# fills the screen
png = PNG(display)
png.open_file('background.png')
png.decode(0, 0)
display.update()

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
settingsFromServer = False
measErrorCnt:int = 0
feed_wdt(useWdt=USE_WDT,wdt=wdt)
runLog(file=logFile,string="STAT|main: beforeLoop\n")
while True:
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    loopCount += 1 # ints in micropython can be huge. Will not have an overflow issue
    runLog(file=logFile,string="{:8.0f} ".format(loopCount))

    if not wlan_check(DEBUG_CFG=DEBUG_CFG,useWdt=USE_WDT,wdt=wdt,wlan=wlan,logFile=logFile):
        runLog(file=logFile,string='A')
        del wlan
        feed_wdt(useWdt=USE_WDT,wdt=wdt)        
        wlan = wlan_init(DEBUG_CFG=DEBUG_CFG,WLAN_CFG=WLAN_CFG,useWdt=USE_WDT,wdt=wdt,logFile=logFile) # may take some time
        runLog(file=logFile,string="B\n")
        continue # let's start a fresh loop

    runLog(file=logFile,string='c')
    ## do it once, shortly (3 mins) after booting, then don't do it for about 24 hours
    if ((time() - timeAtLastOtaCheck) > otaCheckAfterXseconds):
        runLog(file=logFile,string='D')
        timeAtLastOtaCheck = time() # reset the counter
        otaCheckAfterXseconds = 86400 # 24h
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        do_ota(DEBUG_CFG) # maybe reboots, maybe not
        runLog(file=logFile,string="E\n")
        continue
    
    runLog(file=logFile,string='f')
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    meas = json_get_req(DEBUG_CFG=DEBUG_CFG,local_ip=DEVICE_CFG['local_ip'],logFile=logFile) #runLog g
    runLog(file=logFile,string='h')
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    if meas['valid']:
        runLog(file=logFile,string='i')
        measErrorCnt = 0
    else:
        measErrorCnt += 1
        runLog(file=logFile,string='WARN|main: request did not work, trying again. measErrorCnt='+str(measErrorCnt)+"\n")
        if measErrorCnt > 5:
            runLog(file=logFile,string="ERROR|main: main loop, meas error count > 5. Resetting...\n")
            sleep(20) # may trigger the watchdog and force a reboot
            machine.reset()
        sleep(2)
        runLog(file=logFile,string="k\n")
        continue

    runLog(file=logFile,string='l')
    wattVal = int(1000.0 * (-1.0*meas['power_pos'] + meas['power_neg'])) # cons is negative, gen positive. 0 is treated as gen
    runLog(file=logFile,string="Watt: {:6.0f} ".format(wattVal)) # to file and to serial. No newline in the string itself
    if (abs(wattVal) < WATT_NOISE_LIMIT): # everything below this is just noise...
        wattVal = 0
    # print('wattValue: '+str(wattVal))
    
    minValCon = int(settings['minValCon']) # this is a positive value but needs to be treated negative in some cases
    maxValGen = int(settings['maxValGen'])

    runLog(file=logFile,string='m')
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
    disp_y_range = getDispYrange(values=wattValsNonNorm, BAR_HEIGHT=BAR_HEIGHT)
    runLog(file=logFile,string='n')
    x = 0
    color_pen = WHITE
    valHeight = 0
    wattValNorm, wattValNonNorm = 0,0
    length = len(wattValsNorm) # length of both arrays are equal
    #print('value,height: ',end='')# debug
    for i in range(length):
        wattValNorm = wattValsNorm[i] # this is used for the color
        wattValNonNorm = wattValsNonNorm[i] # this is used for the size

        # cons grow down (so plus direction in pixels), gen grow up (so need to 'invert' everything). Full range is (min+max Vals)
        color_pen = display.create_pen(*val_to_rgb(val=wattValNorm, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255))
        display.set_pen(color_pen)
        
        valHeight = int(float(abs(wattValNonNorm)) * disp_y_range) # between 0 and BAR_HEIGHT. E.g. 135*2827/3400        
        #print(str(wattValNonNorm)+' '+str(valHeight),end=' ')# debug
        if wattValNonNorm < 0: 
            display.rectangle(x, MIDDLE, BAR_WIDTH, valHeight)
        else: # direction goes up
            display.rectangle(x, MIDDLE-valHeight, BAR_WIDTH, valHeight)
        x += BAR_WIDTH
    #print('.')# debug
    runLog(file=logFile,string='o')
    valColor = val_to_rgb(val=wattValMinMax, minValCon=minValCon, maxValGen=maxValGen, led_brightness=255)
    # draw the zero line in the current color (1 pix)
    display.set_pen(display.create_pen(*valColor))
    display.rectangle(0, MIDDLE, WIDTH, 1)

    feed_wdt(useWdt=USE_WDT,wdt=wdt)

    if wattVal < 0: display.set_pen(COLOR_MINUS)
    else:           display.set_pen(COLOR_PLUS)
    wattVal4digits = min(abs(wattVal), 9999) # limit it to 4 digits, range 0...9999. Sign is lost

    # writes the reading as text in the rectangle
    txtNum = str(wattVal4digits)
    x, y, w, h = vector.measure_text(txtNum, x=44, y=32, angle=None)
    w = int(w)

    wOutline = Polygon()
    wOutline.rectangle(1,1,127,36, corners=(2, 2, 2, 2), stroke=2)
    vector.draw(wOutline)
    display.set_pen(WHITE)
    vector.text('W', 97, 27, 0)
    vector.text(txtNum, 87-w, 26, 0)
    
    earnTxt = '{0:.2f}'.format(settings['earn'])
    x, y, w, h = vector.measure_text(earnTxt, x=196, y=227, angle=None)
    w = int(w)
    runLog(file=logFile,string='p')
    display.set_pen(WHITE)
    if settingsFromServer: # otherwise don't display the earning text
        vector.text('CHF',201,229,0)
        vector.text(earnTxt,306-w,227,0)

    vector.text(str(loopCount), 10, 229, 0)
    
    display.update()

    # lets also set the LED to match. It's pulsating when we are generating, it's constant when consuming
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    brightness, pulsed = getBrightness(setting=int(settings['brightness']),time=meas['date_time'],wattVal=wattVal,logFile=logFile) # dependency on time
    # print('brightness output: wattVal:settings:applied'+str(wattVal)+':'+str(settings['brightness'])+':'+str(brightness))
    
    rgb_led.control(
        allOk=bool(settings['serverOk']), # NB: meas['valid'] is True. Otherwise we break the loop
        pulsating=pulsed,
        color=val_to_rgb(
            val=wattValMinMax,
            minValCon=minValCon,
            maxValGen=maxValGen,
            led_brightness=int(brightness/2))) # led is quite bright when shining constantly
    runLog(file=logFile,string='q')
    if ((time() - timeAtLastTransmit) > TRANSMIT_EVERY_X_SECONDS):
        runLog(file=logFile,string='R')
        timeAtLastTransmit = time() # reset the counter
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
        settings = tx_to_server(DEBUG_CFG=DEBUG_CFG,DEVICE_CFG=DEVICE_CFG,meas=meas,loopCount=loopCount,
                                useWdt=USE_WDT,wdt=wdt,logFile=logFile) # now transmit the stuff to the server. runLog S/T/U
        settingsFromServer = True
        runLog(file=logFile,string='V')
        feed_wdt(useWdt=USE_WDT,wdt=wdt)
    
    try:
        runLog(file=logFile,string='w')
        del x,y,w,h,wattValNorm,meas,wattVal,minValCon,maxValGen,wattValMinMax,valColor,disp_y_range,length,wattValNonNorm
        del color_pen,valHeight,wattVal4digits,txtNum,wOutline,earnTxt,brightness,pulsed # to combat memAlloc issues
    except:
        runLog(file=logFile,string="ERROR|main: Exception at garbage collection\n")
    gc.collect() # garbage collection
    
    runLog(file=logFile,string='x')
    runLog(file=logFile,string="   \n") # add spaces to increase length
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    sleep(LOOP_SLEEP_SEC)
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
    sleep(LOOP_SLEEP_SEC)
    feed_wdt(useWdt=USE_WDT,wdt=wdt)
