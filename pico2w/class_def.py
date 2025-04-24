# using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# does not work: Micropython version Raspberry Pico 2 W with Pimoroni libraries 0.0.11
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

from math import sin
from machine import Timer # type: ignore
from pimoroni import RGBLED  # type: ignore (included in uf2 file)

class RgbLed(object):

    def __init__(self):
        self.led_rgb = RGBLED(6, 7, 8)
        self.timer_rgb = Timer() # no need to specify a number on pico, all SW timers
        self.color = (0,0,0)
        self.rgb   = (0,0,0)
        self.freq = 5
        self.sineX = 0.0
        self.timerIsInitialized = False

    def pulse_cb(self, noIdeaWhyThisIsNeeded):
        if self.sineX < 5.0: # (smaller than 2*pi)
            self.sineX += 0.02
        else:
            self.sineX = 0
        factor = max(sin(self.sineX), 0.0) # not using abs() because I really want the LED to be off for half the time, to clearly distinguish between cons and gen.
        self.rgb = ((int)(factor*self.color[0]),
                    (int)(factor*self.color[1]),
                    (int)(factor*self.color[2]))
        
        self.led_rgb.set_rgb(*(self.rgb))
        
    def control(self, valid:bool, pulsating:bool, color):
        if not valid:
            self.color = (240, 0, 0)
            self.freq = 100
            self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
            self.timerIsInitialized = False # always do a fresh init for the error case. Don't check the isInitialized value
            return

        if pulsating:
            self.color = color
            self.freq = 30
            if not (self.timerIsInitialized):
                self.timer_rgb.init(freq=self.freq, callback=self.pulse_cb)
                self.timerIsInitialized = True
        else:
            self.timer_rgb.deinit() # not always needed
            self.timerIsInitialized = False
            self.led_rgb.set_rgb(*color)

