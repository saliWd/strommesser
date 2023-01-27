# checks the different timescales
# returns true if test is passing, false otherwise

# action                         
#-------------------------------
# 1) TODO

def doTimescales(driver, testNum):
  from functions import printOkOrNot
 
  modDescription = [(str(testNum)+".1"), "6h_scale_fakeTest"]  
  
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])
  
  # end if
  return True
# end def
