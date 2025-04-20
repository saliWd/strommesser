# not using MicroPython feature/psram-and-wifi, pico2_w v0.0.11 on 2024-11-26; Raspberry Pi Pico 2 W with RP2350
# using micropython from https://www.raspberrypi.com/documentation/microcontrollers/micropython.html
# version: MicroPython v1.25.0-preview.49.g0625f07ad.dirty on 2024-11-21; Raspberry Pi Pico 2 W with RP2350
# make sure hotspot is using 2.4 GHz, otherwise gettings status = -2 (although that status value should not be existing)
# issue with micropython_ota. Cannot install it (cannot find it)


import time
import network # type: ignore (this is a pylance ignore warning directive)

wlan = network.WLAN(network.STA_IF)
wlan.active(True)
time.sleep(1)
wlan.connect('strommesser.ch', 'messerPW') # public password

# Wait for connect or fail
waitCounter = 10
while waitCounter > 0:
    if wlan.status() < 0 or wlan.status() >= 3:
        break
    waitCounter -= 1
    print('waiting for connection... counter: '+str(waitCounter))
    time.sleep(1)

# Handle connection error
if wlan.status() != 3:
    print('WLAN Status: ')
    print(wlan.status())
    raise RuntimeError('network connection failed')
else:
    print('connected')
    status = wlan.ifconfig()
    print( 'ip = ' + status[0] )
