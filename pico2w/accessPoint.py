# not using MicroPython v1.25.0 on 2025-04-15; Raspberry Pi Pico 2 W with RP2350
# working pimoroni libraries: MicroPython pico2_w_2025_04_09,   on 2025-04-15; Raspberry Pi Pico 2 W with RP2350 from https://github.com/pimoroni/pimoroni-pico-rp2350/releases

import network # type: ignore
import time
import socket


def web_page():
  html = """<html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
            <body><h1>StromMesser web page</h1></body>
            </html>
         """
  return html

# issues on this implementation
# - wlan needs a password
# - client must connect to the given ip (e.g. 192.168.4.1). Can I at least define this IP?
# - mobile might switch to another WLAN as this one does not provide internet connection
# - ...need to move some stuff out of the function... Currently a tech demo.
def ap_mode(ssid:str, password:str): # password must be at least 8 chars long
    # attention: function does not return
    
    ap = network.WLAN(network.AP_IF) # access point
    ap.config(essid=ssid, password=password)
    ap.active(True)

    while ap.active() == False:
        pass
    print('AP Mode Is Active, You can Now Connect')
    print('IP Address To Connect to: ' + ap.ifconfig()[0])

    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)   #creating socket object
    s.bind(('', 80))
    s.listen(5)

    while True:
      conn, addr = s.accept()
      print('Got a connection from %s' % str(addr))
      request = conn.recv(1024)
      print('Content = %s' % str(request))
      response = web_page()
      conn.send(response)
      conn.close()
      time.sleep(3)

ap_mode(ssid='strommesser',password='strommesser')

