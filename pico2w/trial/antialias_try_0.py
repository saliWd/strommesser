import time
import random
from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2, PEN_RGB332 # type: ignore
from picovector import PicoVector, RegularPolygon, Rectangle, ANTIALIAS_X4 # type: ignore


display = PicoGraphics(DISPLAY_PICO_DISPLAY_2, pen_type=PEN_RGB332)
display.set_backlight(0.8)

vector = PicoVector(display)
vector.set_antialiasing(ANTIALIAS_X4)


RED = display.create_pen(255, 0, 0)
ORANGE = display.create_pen(255, 128, 0)
YELLOW = display.create_pen(255, 255, 0)
GREEN = display.create_pen(0, 255, 0)
BLUE = display.create_pen(0, 0, 255)
VIOLET = display.create_pen(255, 0, 255)

BLACK = display.create_pen(0, 0, 0)
GREY = display.create_pen(128, 128, 128)
WHITE = display.create_pen(255, 255, 255)
# result = vector.set_font("/AdvRe.af", 30)

WIDTH, HEIGHT = display.get_bounds()

def random_polygon(length, x, y, w, h):
    for i in range(length):
        yield random.randint(x, x + w), random.randint(y, y + h)


hub = RegularPolygon(int(WIDTH / 2), int(HEIGHT / 2), 36, 5)
hub2 = RegularPolygon(int(WIDTH / 2), int(HEIGHT / 2), 36, 10)

#p = RegularPolygon(0, 0, 6, 100)
a = 0

print(time.localtime())

while True:
    year, month, day, hour, minute, second, _, _, _ = time.localtime()

    #p = Polygon(*random_polygon(10, 0, 0, WIDTH, HEIGHT))
    display.set_pen(BLACK)
    display.clear()
    display.set_pen(ORANGE)

    tick_mark = Rectangle(int(WIDTH / 2) - 1, 0, 2, 10)
    for _ in range(12):
        vector.rotate(tick_mark, 360 / 12.0, int(WIDTH / 2), int(HEIGHT / 2))
        vector.draw(tick_mark)
        

    angle_second = second * 6
    second_hand = Rectangle(-1, -100, 2, 100)
    vector.rotate(second_hand, angle_second, 0, 0)
    vector.translate(second_hand, int(WIDTH / 2), int(HEIGHT / 2))

    angle_minute = minute * 6
    angle_minute += second / 10.0
    minute_hand = Rectangle(-2, -70, 4, 70)
    vector.rotate(minute_hand, angle_minute, 0, 0)
    vector.translate(minute_hand, int(WIDTH / 2), int(HEIGHT / 2))

    angle_hour = (hour % 12) * 30
    angle_hour += minute / 2
    hour_hand = Rectangle(-3, -40, 6, 40)
    vector.rotate(hour_hand, angle_hour, 0, 0)
    vector.translate(hour_hand, int(WIDTH / 2), int(HEIGHT / 2))

    display.set_pen(GREEN)
    vector.draw(second_hand,minute_hand, hour_hand)
    display.set_pen(BLACK)
    vector.draw(hub2)
    display.set_pen(WHITE)
    vector.draw(hub)
    display.update()
    time.sleep(1.0 / 30)
    a += 1