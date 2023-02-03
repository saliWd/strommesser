import time
from selenium.webdriver.common.by import By

# 1. does a login
# 2. selects the different timescales
# 3. open settings
# 4. does a logout
# ... and saves all page source code as static files


def writeFile(fileName, fileContent):
    from os.path import abspath, join, dirname
    new_file = abspath(join(dirname(__file__), fileName))
    new_file_open = open(new_file, 'wb')
    new_file_open.write(fileContent.encode('utf8'))
    new_file_open.close()

def getPage(driver, page, testNum, subTest):
  from functions import printOkOrNot
  driver.get('https://strommesser.ch/verbrauch/'+page+'.php')
  # time.sleep(1)
  writeFile(fileName='staticHtml/static.'+page, fileContent=driver.page_source)    
  modDescription = [(str(testNum)+"."+str(subTest)), 'getStatic_'+page] 
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])
  return subTest + 1
# end def  

def getStatic(driver, testNum):
  from functions import doLoginCorrect, printOkOrNot
  subTest = 1
  
  driver.get("https://strommesser.ch/verbrauch/login.php")
  # time.sleep(1)
  writeFile(fileName='staticHtml/static.login', fileContent=driver.page_source)    
  modDescription = [(str(testNum)+"."+str(subTest)), "getStatic_login"] 
  
  doLoginCorrect(driver, modDescription) # after this, I'm on index.php site
  subTest = subTest + 1

  ranges = ('1h','6h','24h','25h')
  
  for range in ranges:
    menuLink = driver.find_element(By.ID, 'range_'+range+'_link')
    menuLink.click()
    # time.sleep(1)
    writeFile(fileName='staticHtml/static.index.'+range, fileContent=driver.page_source)    

    modDescription = [(str(testNum)+"."+str(subTest)), "getStatic_range_"+range] 
    printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])

    subTest = subTest + 1
  # end for

  subTest = getPage(driver, 'settings', testNum, subTest)
  subTest = getPage(driver, 'statistic', testNum, subTest)


  return True
# end def
