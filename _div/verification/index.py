import time
from selenium.webdriver.common.by import By

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

def getStatic(driver, testNum):
  from functions import checkSiteTitleAndPrint, doLoginCorrect
 
  modDescription = [(str(testNum)+".1"), "getStatic_6h"] 

  doLoginCorrect(driver, modDescription)
  # now on index site
  ranges = ('1h','6h','24h','25h')
 
  from os.path import abspath, join, dirname
  for range in ranges:
    menuLink = driver.find_element(By.ID, 'range_'+range+'_link')
    menuLink.click()
    time.sleep(2)
    sourceCode = driver.page_source

    new_file = abspath(join(dirname(__file__), 'staticHtml/index.php.'+range+'.static'))
    new_file_open = open(new_file, 'w')
    new_file_open.write(sourceCode)
    new_file_open.close()

  return True
# end def
