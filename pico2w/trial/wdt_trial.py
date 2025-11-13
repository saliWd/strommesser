# save as main.py

import machine # type: ignore
from time import sleep

errorLog = open('error.log', 'a') # append
string = "\nreset reason: "+str(machine.reset_cause())+"\n"
errorLog.write(string)
print(string,end='')
sleep(5)
wdt = machine.WDT(timeout=8388) # max time, 8.3 sec
wdt.feed()
sleep(10)

string = str(machine.WDT_RESET)+"\nprogram done\n"
print(string,end='')
errorLog.write(string)
