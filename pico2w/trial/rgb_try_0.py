from pimoroni import RGBLED # type: ignore
from time import sleep
led = RGBLED(6, 7, 8)
led.set_rgb(0, 0, 0)
sleep(5)
led.set_rgb(255, 0, 0)
sleep(5)
led.set_rgb(0, 255, 0)
sleep(5)
led.set_rgb(0, 0, 255)
sleep(5)
led.set_rgb(127, 127, 127)
sleep(5)