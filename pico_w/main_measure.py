import network # type: ignore (this is a pylance ignore warning directive)
import urequests # type: ignore
from time import sleep
from machine import Pin, UART, freq # type: ignore


# my own files
import my_config
from my_functions import debug_print, debug_sleep, wlan_connect, urlencode, get_randNum_hash


def uart_ir_e350(DBGCFG:dict, uart_ir):
    if(DBGCFG["ir_sim"]):
        return('/LGZ4ZMF100AC.M26\r\n\x02F.F(00)\r\n0.0(          120858)\r\nC.1.0(13647123)\r\nC.1.1(        )\r\n1.8.1(042951.721*kWh)\r\n1.8.2(018609.568*kWh)\r\n2.8.1(000000.302*kWh)\r\n2.8.2(000010.188*kWh)\r\n1.8.0(061561.289*kWh)\r\n2.8.0(000010.490*kWh)\r\n15.8.0(061571.780*kWh)\r\nC.7.0(0008)\r\n32.7(241*V)\r\n52.7(243*V)\r\n72.7(242*V)\r\n31.7(000.35*A)\r\n51.7(000.52*A)\r\n71.7(000.47*A)\r\n82.8.1(0000)\r\n82.8.2(0000)\r\n0.2.0(M26)\r\nC.5.0(0401)\r\n!\r\n\x03\x01')
    if (uart_ir.any() != 0):
        uart_ir.read() # first clear everything. This should return None. Timeout set to 6s
        print('Warning: UART buffer was not empty at first read')
    uart_ir.write('\x2F\x3F\x21\x0D\x0A') # in characters: '/?!\r\n'
    sleep(1) # need to make sure it has been sent but not wait more than 2 secs. TODO: maybe use uart_ir.flush()
    uart_str_id = uart_ir.read() # should be b'/LGZ4ZMF100AC.M26\r\n' (this part is not being transmitted)
    uart_ir.write('\x06\x30\x30\x30\x0D\x0A') # in characters: ACK000\r\n
    sleep(2) 
    uart_str_values_0 = uart_ir.read()
    sleep(2) 
    uart_str_values_1 = uart_ir.read()
    sleep(2) 
    if (uart_ir.any() != 0):
        print('Warning: UART buffer is not empty after two reads')
    if ((uart_str_id == None) or (uart_str_values_0 == None) or (uart_str_values_1 == None)):
        print('Error: uart communication did not work')
        if (uart_str_id != None): print('error, id='+uart_str_id.decode())
        if (uart_str_values_0 != None): print('error, uart_str_values_0='+uart_str_values_0.decode())
        if (uart_str_values_1 != None): print('error, uart_str_values_1='+uart_str_values_1.decode())
        return('uart communication did not work') # still a string, will not be transmitted ()
    else:
        return(uart_str_values_0.decode()+uart_str_values_1.decode())

def invalidUartStr(uart_received_str:str):
    return(len(uart_received_str) < 40) # catches the (one-of-the UART receives has been None) but does not catch whether all params have been transmitted (might vary from device to device)

def send_message_and_wait_post(DBGCFG:dict, message:dict, wait_time:int, led_onboard):
    # about TXVER: integer (range 0 to 9), increases when there is a change on the transmitted value format 
    # 0 is doing GET-communication, 1 uses post to transmit an identifier, values as blob
    # 2 uses authentification with a hash when sending
    if(not DBGCFG["wlan_sim"]): # not sending anything in simulation
        URL = "https://strommesser.ch/verbrauch/rx_v2.php?TX=pico&TXVER=2"
        HEADERS = {'Content-Type':'application/x-www-form-urlencoded'}

        urlenc = urlencode(message)
        response = urequests.post(URL, data=urlenc, headers=HEADERS)
        debug_print(DBGCFG=DBGCFG, text="Text:"+response.text)
        response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
    debug_sleep(DBGCFG=DBGCFG,time=wait_time)
    led_onboard.toggle() # signal success

DBGCFG = my_config.get_debug_settings() # debug stuff

PWR_INCR = True # need a higher power consumption as the solar power manager shuts itself down when output is less than ~40 mA
if PWR_INCR:
    Pio10 = Pin(10, Pin.OUT)
    Pio18 = Pin(18, Pin.OUT)
    Pio10.on() # connect a resistor to ground on those two pins to drain more power
    Pio18.on()
    freq(240000000) # set CPU clock to 240 MHz to consume more power

LOOP_WAIT_TIME = 40

# pins
led_onboard = Pin("LED", Pin.OUT)

# machine specific stuff
uart_ir = UART(0, baudrate=300, bits=7, parity=0, stop=1, tx=Pin(0), rx=Pin(1), timeout=6000)

## program starts here
led_onboard.on()

wlan = network.WLAN(network.STA_IF)
wlan.active(True)
sleep(3)

device_config = my_config.get_device_config()

while True:
    uart_received_str = uart_ir_e350(DBGCFG=DBGCFG, uart_ir=uart_ir) # this takes some seconds
    
    debug_print(DBGCFG=DBGCFG, text=uart_received_str)

    # basic check, based on string length
    if (invalidUartStr(uart_received_str=uart_received_str)):
        print('Warning: uart string not as expected')
        debug_sleep(DBGCFG=DBGCFG, time=LOOP_WAIT_TIME)
        continue
    
    randNum_hash = get_randNum_hash(device_config)
    
    message = dict([
        ('device', device_config['device_name']),
        ('ir_answer', uart_received_str),
        ('randNum', randNum_hash['randNum']),
        ('hash', randNum_hash['hash'])
        ])
    debug_print(DBGCFG=DBGCFG, text=str(message))
    wlan_connect(DBGCFG=DBGCFG, wlan=wlan, led_onboard=led_onboard, meas=True) # try to connect to the WLAN. Hangs there if no connection can be made
    send_message_and_wait_post(DBGCFG=DBGCFG, message=message, wait_time=LOOP_WAIT_TIME, led_onboard=led_onboard) # does not send anything when in simulation
# end while
