# def getRestValues(): 
#     URL = "http://192.168.178.47/api/v1/report" # local network
#     try:
#         # this is the most critical part. does not work when no-WLAN or no-Server or pico-issue 
#         response = urequests.get(url=URL)
#         if (response.status_code != 200):
#             print("invalid status code. Resetting in 20 seconds...")
#             sleep(20)             
#             reset() # NB: connection to whatever device is getting lost; complicates debugging
#         returnText = response.text
#         print("debugprint   Text:"+returnText)
#         response.close() # this is needed, I'm getting outOfMemory exception otherwise after 4 loops
#         return(returnText)
#     except:
#         print("got an exception. Resetting in 20 seconds...") 
#         sleep(20) # add a bit of debug possibility        
#         reset() # NB: connection to whatever device is getting lost; complicates debugging
#         return # this return will never be executed
