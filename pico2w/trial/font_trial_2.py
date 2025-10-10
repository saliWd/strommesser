'''
Fonts for the pico display!
Les Wright 2021
V 1.1
Update with a suggestion from Steve Borg:
https://forums.pimoroni.com/t/pico-display-and-fonts/16194/18

"Refactor it slightly so that the various functions take an optional parameter to tell it
whether to do a display.update() you can get it so it draws much more quickly.
That way those that like the teleprompter type output can leave it to update after each character
and those that don’t could update after each string."

printchar naw has the extra parameter: Charupdate (Boolean (True/False)
printchar(letter,xpos,ypos,size,charupdate)
delchar has the same
delchar(xpos,ypos,size,delupdate)

Printstring has two extra args charupdate and strupdate (Boolean (True/False)
printstring(string,xpos,ypos,size,charupdate,strupdate)  
These say whether to call display.update()on individual chars (slow) or on the entire string (fast)
You can set them both to false if you want, send multiple lines, then do a display.upadte() manually if you like
(see example code below)

'''


from picographics import PicoGraphics, DISPLAY_PICO_DISPLAY_2, PEN_RGB565  # type: ignore
display = PicoGraphics(display=DISPLAY_PICO_DISPLAY_2, rotate=0)#, pen_type=PEN_RGB565)
WIDTH, HEIGHT = display.get_bounds() # 320x240
BLACK = display.create_pen(0,0,0)
WHITE = display.create_pen(255,255,255)
# Initialise display with a bytearray display buffer
buf = bytearray(WIDTH * HEIGHT * 2)
# display.init(buf)
display.set_backlight(0.5)

# sets up a handy function we can call to clear the screen
def clear():
    display.set_pen(BLACK)
    display.clear()
    display.update()
    
clear()

#ASCII Character Set
cmap = ['00000000000000000000000000000000000', #Space
        '00100001000010000100001000000000100', #!
        '01010010100000000000000000000000000', #"
        '01010010101101100000110110101001010', ##
        '00100011111000001110000011111000100', #$
        '11001110010001000100010001001110011', #%
        '01000101001010001000101011001001101', #&
        '10000100001000000000000000000000000', #'
        '00100010001000010000100000100000100', #(
        '00100000100000100001000010001000100', #)
        '00000001001010101110101010010000000', #*
        '00000001000010011111001000010000000', #+
        '000000000000000000000000000000110000100010000', #,
        '00000000000000011111000000000000000', #-
        '00000000000000000000000001100011000', #.
        '00001000010001000100010001000010000', #/
        '01110100011000110101100011000101110', #0
        '00100011000010000100001000010001110', #1
        '01110100010000101110100001000011111', #2
        '01110100010000101110000011000101110', #3
        '00010001100101011111000100001000010', #4
        '11111100001111000001000011000101110', #5
        '01110100001000011110100011000101110', #6
        '11111000010001000100010001000010000', #7
        '01110100011000101110100011000101110', #8
        '01110100011000101111000010000101110', #9
        '00000011000110000000011000110000000', #:
        '01100011000000001100011000010001000', #;
        '00010001000100010000010000010000010', #<
        '00000000001111100000111110000000000', #=
        '01000001000001000001000100010001000', #>
        '01100100100001000100001000000000100', #?
        '01110100010000101101101011010101110', #@
        '00100010101000110001111111000110001', #A
        '11110010010100111110010010100111110', #B
        '01110100011000010000100001000101110', #C
        '11110010010100101001010010100111110', #D
        '11111100001000011100100001000011111', #E
        '11111100001000011100100001000010000', #F
        '01110100011000010111100011000101110', #G
        '10001100011000111111100011000110001', #H
        '01110001000010000100001000010001110', #I
        '00111000100001000010000101001001100', #J
        '10001100101010011000101001001010001', #K
        '10000100001000010000100001000011111', #L
        '10001110111010110101100011000110001', #M
        '10001110011010110011100011000110001', #N
        '01110100011000110001100011000101110', #O
        '11110100011000111110100001000010000', #P
        '01110100011000110001101011001001101', #Q
        '11110100011000111110101001001010001', #R
        '01110100011000001110000011000101110', #S
        '11111001000010000100001000010000100', #T
        '10001100011000110001100011000101110', #U
        '10001100011000101010010100010000100', #V
        '10001100011000110101101011101110001', #W
        '10001100010101000100010101000110001', #X
        '10001100010101000100001000010000100', #Y
        '11111000010001000100010001000011111', #Z
        '01110010000100001000010000100001110', #[
        '10000100000100000100000100000100001', #\
        '00111000010000100001000010000100111', #]
        '00100010101000100000000000000000000', #^
        '00000000000000000000000000000011111', #_
        '11000110001000001000000000000000000', #`
        '00000000000111000001011111000101110', #a
        '10000100001011011001100011100110110', #b
        '00000000000011101000010000100000111', #c
        '00001000010110110011100011001101101', #d
        '00000000000111010001111111000001110', #e
        '00110010010100011110010000100001000', #f
        '000000000001110100011000110001011110000101110', #g
        '10000100001011011001100011000110001', #h
        '00100000000110000100001000010001110', #i
        '0001000000001100001000010000101001001100', #j
        '10000100001001010100110001010010010', #k
        '01100001000010000100001000010001110', #l
        '00000000001101010101101011010110101', #m
        '00000000001011011001100011000110001', #n
        '00000000000111010001100011000101110', #o
        '000000000001110100011000110001111101000010000', #p
        '000000000001110100011000110001011110000100001', #q
        '00000000001011011001100001000010000', #r
        '00000000000111110000011100000111110', #s
        '00100001000111100100001000010000111', #t
        '00000000001000110001100011001101101', #u
        '00000000001000110001100010101000100', #v
        '00000000001000110001101011010101010', #w
        '00000000001000101010001000101010001', #x
        '000000000010001100011000110001011110000101110', #y
        '00000000001111100010001000100011111', #z
        '00010001000010001000001000010000010', #{
        '00100001000010000000001000010000100', #|
        '01000001000010000010001000010001000', #}
        '01000101010001000000000000000000000' #}~
]

def printchar(letter,xpos,ypos,size,charupdate):
    origin = xpos
    charval = ord(letter)
    #print(charval)
    index = charval-32 #start code, 32 or space
    #print(index)
    character = cmap[index] #this is our char...
    rows = [character[i:i+5] for i in range(0,len(character),5)]
    #print(rows)
    for row in rows:
        #print(row)
        for bit in row:
            #print(bit)
            if bit == '1':
                display.pixel(xpos,ypos)
                if size==2:
                    display.pixel(xpos,ypos+1)
                    display.pixel(xpos+1,ypos)
                    display.pixel(xpos+1,ypos+1)
            xpos+=size
        xpos=origin
        ypos+=size
    if charupdate == True:
        display.update()
    
def delchar(xpos,ypos,size,delupdate):
    charwidth = 5
    charheight = 9
    if size == 2:
        charwidth = 10
        charheight = 18
    display.set_pen(BLACK)
    display.rectangle(xpos,ypos,charwidth,charheight) #xywh
    if delupdate == True:
        display.update()


def printstring(string,xpos,ypos,size,charupdate,strupdate):
    if size == 2:
        spacing = 14
    else:
        spacing = 8
    for i in string:
        printchar(i,xpos,ypos,size,charupdate)
        xpos+=spacing
    if strupdate == True:
        display.update()


#display one char at a time, like an old serial term...
test1 = "Hello World!!"
display.set_pen(WHITE)
printstring(test1,0,0,2,True,False)


#display string fast
test2 = "Les Wright 2021"
display.set_pen(WHITE)
printstring(test2,0,20,2,False,True)

#display one char at a time, like an old serial term...
test3 = 'abcdefghijk'
display.set_pen(WHITE)
printstring(test3,0,60,2,True,False)

#display one char at a time, like an old serial term...
test4 = 'lmnopqrstuvwxyz'
display.set_pen(WHITE)
printstring(test4,0,80,2,True,False)

#display blocks of text fast
test5 = '`~!@#$%^&*()_-+=['
printstring(test5,0,100,2,False,False)
test6 = ']\\{}|;\':"<>?,./'
printstring(test6,0,120,2,False,False)
display.update()


spinner = '-\|/-\|/' # type: ignore
for i in range(50):
    for x in spinner:
        display.set_pen(WHITE)
        printchar(x,220,20,2,True)
        delchar(220,20,2,True)
       

