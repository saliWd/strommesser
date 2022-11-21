import network # type: ignore (this is a pylance ignore warning directive)
from time import sleep
from machine import Pin, freq # type: ignore

# pins
led_onboard = Pin("LED", Pin.OUT)
enable3v3_pin = Pin(28, Pin.OUT) # solder pin GP28 to '3V3_EN'-pin

pio10 = Pin(10, Pin.OUT)
pio18 = Pin(18, Pin.OUT)

led_onboard.on()
pio10.on()
pio18.on()

wlan = network.WLAN(network.STA_IF)
wlan.active(True) # to have comparable consumption
sleep(1)

freq(200000000) # set CPU clock to 200 MHz to consume more power

while True:
    led_onboard.on()
    sleep(3)
    led_onboard.off()
    sleep(3)
# end while
