## xx_version_placeholder_xx
import micropython_ota # type: ignore
from my_functions import wlan_connect

# connect to network
wlan_connect() # try to connect to the WLAN. Hangs/resets if no connection can be made

micropython_ota.ota_update(
    host='https://strommesser.ch/pico_w_ota/', 
    project='display', 
    filenames=['boot.py', 'main.py', 'my_functions.py'], 
    use_version_prefix=False
)
