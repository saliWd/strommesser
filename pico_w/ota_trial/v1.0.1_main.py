import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
# ota part
import micropython_ota   # type: ignore

# import mip
# mip.install('github:olivergregorius/micropython_ota/micropython_ota.py')
## NB: when getting an "OSError: -6" --> issue with firewall or similar, when connected to hotspot this works


def wlan_connect(wlan):
    wlan_ok_flag = wlan.isconnected()
    if(wlan_ok_flag):
        return() # nothing to do
    else: # wlan is not ok
        for i in range(100): # set the time out
            wlan.connect('strommesser', 'publicPW')
            sleep(5)
            wlan_ok_flag = wlan.isconnected()
            print("WLAN connected? "+str(wlan_ok_flag)+", loop var: "+str(i)) # debug output
            if (wlan_ok_flag):
                return 
        # timeout, did not manage to get a working WLAN
        from machine import reset # type: ignore
        reset() # NB: connection to whatever device is getting lost; complicates debugging


VERSION_STRING = 'v1.0.1'

wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(1)

ota_host = 'https://strommesser.ch/verbrauch/pico_w/'
project_name = 'ota_trial'

i_whileLoop = 0

while True:
    wlan_connect(wlan=wlan) # try to connect to the WLAN. Hangs there if no connection can be made
    print("Version string: "+VERSION_STRING+", loop var: "+str(i_whileLoop))
    sleep(5)

    micropython_ota.check_for_ota_update(ota_host, project_name, soft_reset_device=False, timeout=10)

    # this code should only be reached when no new version is available
    print("Version string: "+VERSION_STRING+", loop var: "+str(i_whileLoop))
    sleep(20)
    i_whileLoop = i_whileLoop + 1
