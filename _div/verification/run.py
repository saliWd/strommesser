import sys
from selenium import webdriver
from functions import finish, printOkOrNot


# main file to start other tests
#------------------------------------------------------------------------------
def printUsage(ALL_TESTS):
  print("Usage: run.py [testName]")
  print("run.py: runs all the available tests")
  print("run.py testName: runs a single test")
  print("\n -> available tests are: ", end="")
  for test in ALL_TESTS:
    print(test + " ", end="")
  print(" ")  # just a new line
# end def


def callSingleTest(driver, testNum, ALL_TESTS, testsToRun):
  from loginLogout import doLoginLogout
  from index import doTimescales
  from staticHtml import getStatic
  
  if ALL_TESTS[testNum] in testsToRun:
    if testNum == 0:
      result = doLoginLogout(driver, testNum)      
    elif testNum == 1:
      result = doTimescales(driver, testNum)      
    elif testNum == 2:
      result = getStatic(driver, testNum)
    else:
      result = False
    # end if-elif

    if (not(result)): 
      print("Test "+str(testNum)+" failed. Finishing...")
      finish(driver)
    print("----")
  # end if
# end def


ALL_TESTS = ['loginLogout', 'differentTimescales', 'getStatic']


# input processing
testsToRun = []
if len(sys.argv) < 2:  # this means no argument has been given. Running all tests
  testsToRun = ALL_TESTS  
elif len(sys.argv) == 2:
  if sys.argv[1] in ALL_TESTS:  # find the argument
    testsToRun = [sys.argv[1]]
  else:
    printUsage(ALL_TESTS)
  # end if 
else:
    printUsage(ALL_TESTS)
# end if


# execution of the tests
if len(testsToRun) > 0:
  # Create a new instance of the Firefox driver
  driver = webdriver.Firefox()
  driver.set_window_size(500, 700) # about mobile size, portrait style
  driver.implicitly_wait(5) #wait 5 seconds when doing a find_element before carrying on  

  for i in range(0, len(ALL_TESTS)):
    callSingleTest(driver, i, ALL_TESTS, testsToRun)
  
  printOkOrNot(ok=True, testNum="==>", text="Selected tests execution")
  finish(driver)
# end if len testsToRun
