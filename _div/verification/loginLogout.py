# does the login and logout
# returns true if test is passing, false otherwise

# action                         
#-------------------------------
# 1) login with faulty name/pw
# 2) login with correct name/pw
# 3) logout
def doLoginLogout(driver, testNum):
  from functions import doLogin, doLogout, checkSiteTitleAndPrint
 
  driver.get("https://strommesser.ch/verbrauch/login.php") # go to the login page
  
  modDescription = [(str(testNum)+".1"), "login_with_wrong_password"]  
  doLogin(driver, username="messer@strommesser.ch", password="wrongPassword")
  # we are still on the same page (but only with error messages)
  if (not(checkSiteTitleAndPrint(driver, modDescription, expectedSiteTitle="Error page"))):
    return False
  # end if

  driver.get("https://strommesser.ch/verbrauch/login.php") # go to the login page

  modDescription = [(str(testNum)+".2"), "login_with_correct_password"]  
  doLogin(driver, username="messer@strommesser.ch", password="messerPW") # this is the correct password
  if (not(checkSiteTitleAndPrint(driver, modDescription, expectedSiteTitle="StromMesser Verbrauch"))):
    return False
  # end if

  modDescription = [(str(testNum)+".3"), "log_out"]  
  doLogout(driver)
  if (not(checkSiteTitleAndPrint(driver, modDescription, expectedSiteTitle="StromMesser Log out"))):
    return False
  # end if  
  
  return True
# end def