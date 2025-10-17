import network # type: ignore
from time import sleep

wlanStatus = 0
waitCounter = 0
wlan = network.WLAN(network.WLAN.IF_STA)


print('STAT_IDLE: '+str(network.STAT_IDLE))
print('STAT_CONNECTING: '+str(network.STAT_CONNECTING))
print('STAT_WRONG_PASSWORD: '+str(network.STAT_WRONG_PASSWORD))
print('STAT_NO_AP_FOUND: '+str(network.STAT_NO_AP_FOUND))
print('STAT_CONNECT_FAIL: '+str(network.STAT_CONNECT_FAIL))
print('STAT_GOT_IP: '+str(network.STAT_GOT_IP))


while wlanStatus != network.STAT_GOT_IP: # STAT_GOT_IP = 3, STAT_CONNECTING = 1
    print('waiting for connection...WLAN Status: '+str(wlanStatus)+'. Counter: '+str(waitCounter))
    wlan = network.WLAN(network.WLAN.IF_STA)
    wlan.active(True) # activate it. NB: disabling does not work correctly
    sleep(2)    
    wlan.connect('strommesser.ch', 'messerPW')
    sleep(2)
    wlanStatus = wlan.status()

print('done')