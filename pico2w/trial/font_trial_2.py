from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2 # type: ignore
from picovector import PicoVector, ANTIALIAS_BEST # type: ignore

display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2)

MAGENTA = display.create_pen(255, 0, 255)
BLACK = display.create_pen(0, 0, 0)

vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_BEST)
vector.set_font("font_1.af", 90)
vector.set_font_line_height(80)

display.set_pen(MAGENTA)
display.clear()
display.set_pen(BLACK)
vector.text("Hey Presto!", 0, 160, max_width=240)
display.update()