import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY  # type: ignore

# my own files
import my_config
from my_functions import debug_sleep, wlan_connect

VERSION_STRING = 'v1.0.0'

def make_bold(display, text:str, x:int, y:int): # making it 'bold' by shifting it 1px right (not very nice hack)
    display.text(text, x, y, scale=1.1)
    display.text(text, x+1, y, scale=1.1)

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
# fills the screen with black
display.set_pen(BLACK)
display.clear()
display.update()


# ota part
import micropython_ota   # type: ignore

ota_host = 'https://strommesser.ch/verbrauch/pico_w/'
project_name = 'ota_trial'


while True:
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs there if no connection can be made
    display.set_pen(BLACK)
    display.clear()
    display.set_pen(WHITE)
    display.rectangle(1, 1, 200, 41) # draws a background for the black text
    
    # writes the reading as text in the rectangle
    display.set_pen(BLACK)
    make_bold(display,VERSION_STRING, 7, 23)
    display.update()
    
    micropython_ota.check_for_ota_update(ota_host, project_name, soft_reset_device=False, timeout=10)

    debug_sleep(DBGCFG=DBGCFG,time=LOOP_WAIT_TIME)
