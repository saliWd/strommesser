import time
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# returns a boolean
def checkSiteTitle (driver, expectedSiteTitle, outputOnFail=True):
  try:
    # we have to wait for the page to refresh, the last thing that seems to be updated is the title
    WebDriverWait(driver, 3).until(EC.title_contains(expectedSiteTitle))  # timeout in seconds
    return True
  except: # most probably the timeout exception
    if (outputOnFail):
      print("Site title not as expected: " + driver.title)
    return False
  # end try/except
# end def

def checkSiteTitleAndPrint (driver, modDescription, expectedSiteTitle):  
  if (not(checkSiteTitle(driver, expectedSiteTitle))):
    printOkOrNot(ok=False, testNum=modDescription[0], text=modDescription[1])
    return False
  # end if
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1]) # we are now on the correct page
  return True
# end def

def doLogin (driver, username, password):
  # find the elements I want to control  
  emailField    = driver.find_element(By.NAME, "email")
  passwordField = driver.find_element(By.NAME, "password")  

  # send the login details
  emailField.send_keys(username)
  passwordField.send_keys(password)  

  # and submit
  passwordField.submit() # could also use a command like: driver.find_element_by_name("login").click() 
# end def

def doLogout (driver):
  driver.get("https://strommesser.ch/verbrauch/login.php?do=2") # go to the logout page
  time.sleep(1) # wait until menu did appear
# end def

def finish (driver):
  import sys  
  driver.quit() # close the browser window
  sys.exit()
# end def

def printOkOrNot(ok, testNum, text):
  successPre = "ERROR"
  successPost = " was not successful."  
  if(ok):
    successPre = "OK"
    successPost = " was successful."
  print(testNum + " " + successPre + " " + text + successPost)
# end def

###### tested until here...

#returns a boolean
def siteHasId(driver, idToSearchFor):
  try:    
    element = driver.find_element(By.ID, idToSearchFor)
    return True
  except: # most probably the timeout exception    
    print("the element with this ID has not been found: " + idToSearchFor)
    time.sleep(5) # not needed, to admire the page
    return False
  # end try/except
# end def

def checkSiteHasIdAndPrint(driver, modDescription, idToSearchFor):
  if (not(siteHasId(driver, idToSearchFor))):
    printOkOrNot(ok=False, testNum=modDescription[0], text=modDescription[1])
    return False
  # end if
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])
  return True
# end def


def writeFile(fileName, fileContent):
    from os.path import abspath, join, dirname
    new_file = abspath(join(dirname(__file__), fileName))
    new_file_open = open(new_file, 'wb')
    new_file_open.write(fileContent.encode('utf8'))
    new_file_open.close()

def getPage(driver, page, testNum, subTest):
  from functions import printOkOrNot
  driver.get('view-source:https://strommesser.ch/verbrauch/'+page+'.php')
  pageSource=driver.find_element(By.TAG_NAME, 'html').text
  writeFile(fileName='staticHtml/static.'+page, fileContent=pageSource)

  modDescription = [(str(testNum)+"."+str(subTest)), 'getStatic_'+page] 
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])
  return subTest + 1
# end def  
