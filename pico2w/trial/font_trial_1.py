#IMPORT LIBRARIES
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2 # type: ignore
from picovector import PicoVector, ANTIALIAS_NONE # type: ignore

#SET DISPLAY
display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2)
display.set_backlight(0.8)
#SET VECTOR, ANTIALIASING & FONT TYPE {download.af fonts from resources above. Upload through Thonny in root "/" }
# https://github.com/lowfatcode/alright-fonts/tree/main/sample-fonts
vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_NONE)
result = vector.set_font('font_1.af', 50)  #vector.setfont("font-name-directory",font-size-int)
print(result)
#SET PALETTE
WHITE = display.create_pen(255,255,255)
BLACK = display.create_pen(0,0,0)

#BLACK BG
display.set_pen(BLACK)
display.clear()

#SET PEN AND DISPLAY TEXT - vector.text("TextHere",X,Y,ANGLEDEG)
display.set_pen(WHITE)
vector.text("Hello World!", 60, 90, 0)

#UPDATE SCREEN
display.update()

print('program done')
