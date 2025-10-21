## xx_version_placeholder_xx
from function_def import wlan_init, do_ota
import my_config

# connect to network
DEBUG_CFG  = my_config.get_debug_settings() # debug stuff
WLAN_CFG = my_config.get_wlan_config()

wlan = wlan_init(DEBUG_CFG=DEBUG_CFG, WLAN_CFG=WLAN_CFG)

do_ota(DEBUG_CFG)
