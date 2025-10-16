#IMPORT LIBRARIES
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2 # type: ignore
from picovector import PicoVector, ANTIALIAS_X4 # type: ignore

#SET DISPLAY
display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2)
display.set_backlight(0.8)
# font taken from
# https://github.com/Gadgetoid/alright-fonts/blob/effb2fca35909a0f2aff7ed04b76c14286490817/sample-fonts/IndieFlower/IndieFlower-Regular.af
# probably one of those: "Open Sans" or "Roboto". Thin font: "Alumni Sans Pinstripe"
# could also generate a file for numbers only, see github.com/Gadgetoid/alright-fonts description 

# (https://github.com/lowfatcode/alright-fonts/tree/main/sample-fonts does not work, see https://forums.pimoroni.com/t/presto-and-alright-fonts-issue-picovector/28396/4)

vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_X4)
result = vector.set_font('IndieFlower-Regular.af', 50)  # font from , stored in root on filesystem
print(result)

#SET PALETTE
WHITE = display.create_pen(255,255,255)
GREY = display.create_pen(20,20,20)

# BG
display.set_pen(GREY)
display.clear()

#SET PEN AND DISPLAY TEXT
display.set_pen(WHITE)
vector.text("Hello World!", 60, 90, 0)

#UPDATE SCREEN
display.update()

print('program done')
