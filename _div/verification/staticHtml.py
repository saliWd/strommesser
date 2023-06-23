import time
from selenium.webdriver.common.by import By

# 1. does a login
# 2. selects the different timescales
# 3. open settings
# 4. does a logout
# ... and saves all page source code as static files

def getStatic(driver, testNum):
  from functions import printOkOrNot, checkSiteTitleAndPrint, writeFile, getPage
  from my_config import doLoginCorrect
  subTest = 1
  
  driver.get("view-source:https://strommesser.ch/verbrauch/login.php")
  pageSource=driver.find_element(By.TAG_NAME, 'html').text
  writeFile(fileName='staticHtml/static.login', fileContent=pageSource)    
  modDescription = [(str(testNum)+"."+str(subTest)), "getStatic_login"] 

  driver.get("https://strommesser.ch/verbrauch/login.php") # go to the login page

  doLoginCorrect(driver) 
  if (not(checkSiteTitleAndPrint(driver, modDescription, expectedSiteTitle="StromMesser Verbrauch"))):
    return False
  # end if
  subTest = subTest + 1

  ranges = ('1','6','24','25')
  
  for range in ranges:
    url = '?range='+range
    driver.get("view-source:https://strommesser.ch/verbrauch/index.php"+url)
    pageSource=driver.find_element(By.TAG_NAME, 'html').text

    writeFile(fileName='staticHtml/static.index.'+range+'h', fileContent=pageSource)
    modDescription = [(str(testNum)+"."+str(subTest)), "getStatic_range_"+range] 
    printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])

    subTest = subTest + 1
  # end for

  subTest = getPage(driver, 'settings', testNum, subTest)
  subTest = getPage(driver, 'statistic', testNum, subTest)

  return True
# end def
