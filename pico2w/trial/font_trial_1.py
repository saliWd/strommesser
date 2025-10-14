#IMPORT LIBRARIES
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2 # type: ignore
from picovector import PicoVector, ANTIALIAS_X4 # type: ignore

#SET DISPLAY
display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2)

#SET VECTOR, ANTIALIASING & FONT TYPE {download.af fonts from resources above. Upload through Thonny in root "/" }
# https://github.com/lowfatcode/alright-fonts/tree/main/sample-fonts
vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_X4)
result = vector.set_font("/IndieFlower-Regular.af", 50)  #vector.setfont("font-name-directory",font-size-int)

#SET PALETTE
WHITE = display.create_pen(255,255,255)
BLACK = display.create_pen(0,0,0)

while True:

    #BLACK BG
    display.set_pen(BLACK)
    display.clear()
    
    #SET PEN AND DISPLAY TEXT - vector.text("TextHere",X,Y,ANGLEDEG)
    display.set_pen(WHITE)
    vector.text("Hello World!", 60, 90, 0)

    #UPDATE SCREEN
    display.update()