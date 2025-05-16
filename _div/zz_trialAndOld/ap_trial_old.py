# install micropython-mdns package

import network # type: ignore
import time
import socket


### use phew instead https://picockpit.com/raspberry-pi/raspberry-pi-pico-w-captive-portal-hotspot-access-point-pop-up/

def web_page():
  html = """<html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head>
            <body><h1>StromMesser web page</h1></body>
            </html>
         """
  return html

# issues on this implementation
# - client must connect to the given ip (e.g. 192.168.4.1). Can I at least define this IP?
# - mobile might switch to another WLAN as this one does not provide internet connection
# - ...need to move some stuff into a function... Currently a tech demo.

""" change the default IP
IP =      '192.168.12.1'
SUBNET =  '255.255.255.0'
GATEWAY = '192.168.12.1'
DNS =     '0.0.0.0'

ssid = SSID_AP['ssid']
pw = SSID_AP['pw']

ap = network.WLAN(network.AP_IF)
ap.config(ssid=ssid, password=pw)
ap.active(True)
time.sleep(0.1)
print('1',ap)
ap.ifconfig((IP,SUBNET,GATEWAY,DNS))
time.sleep(0.1)
print('3',ap)
"""



ap = network.WLAN(network.AP_IF) # access point
# not sure what this does... network.hostname('strommesser.local')
ap.config(essid='strommesser', security=0) # open network, no password
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



