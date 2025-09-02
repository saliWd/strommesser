## xx_version_placeholder_xx
import micropython_ota # type: ignore
from function_def import wlan_init
import my_config

# connect to network
DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
WLAN_CFG = my_config.get_wlan_config()

wlan = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG)

micropython_ota.ota_update(
    host='https://strommesser.ch/ota/', 
    project='display', 
    filenames=['boot.py', 'main.py', 'function_def.py', 'class_def.py'], 
    use_version_prefix=False
)

