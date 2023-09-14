import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
import micropython_ota # type: ignore

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


# connect to network
wlan = network.WLAN(network.STA_IF)
wlan.active(True) # activate it. NB: disabling does not work correctly
sleep(1)
wlan_connect(wlan=wlan) # try to connect to the WLAN. Hangs there if no connection can be made


ota_host = 'https://strommesser.ch/verbrauch/pico_w/'
project_name = 'ota_trial'

filenames = ['boot.py', 'main.py']

micropython_ota.ota_update(ota_host, project_name, filenames, use_version_prefix=True, hard_reset_device=True, soft_reset_device=False, timeout=5)

