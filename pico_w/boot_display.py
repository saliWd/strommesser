## xx_version_placeholder_xx
import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
import micropython_ota # type: ignore
from my_functions import wlan_connect

# connect to network
wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(1)
wlan_connect(wlan=wlan, led_onboard=False, meas=False) # try to connect to the WLAN. Hangs/resets if no connection can be made

micropython_ota.ota_update(
    host='https://strommesser.ch/pico_w_ota/', 
    project='display', 
    filenames=['boot.py', 'main.py', 'my_functions.py'], 
    use_version_prefix=False
)
