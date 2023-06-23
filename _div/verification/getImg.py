# from selenium.webdriver.common.by import By
from PIL import Image


# 1. does a login
# 2. selects the different timescales

# 4. does a logout


def getImg(driver, testNum):
  from functions import printOkOrNot, checkSiteTitleAndPrint, getPage
  from my_config import doLoginCorrect
  subTest = 1
  
  modDescription = [(str(testNum)+"."+str(subTest)), "getImg_login"] 
  driver.get("https://strommesser.ch/verbrauch/login.php") # go to the login page

  doLoginCorrect(driver) 
  if (not(checkSiteTitleAndPrint(driver, modDescription, expectedSiteTitle="StromMesser Verbrauch"))):
    return False
  # end if
  subTest = subTest + 1

  driver.set_window_size(1024, 800) # bigger window size

  driver.get("https://strommesser.ch/verbrauch/index.php?range=24")
  driver.save_screenshot('tmp.png')

  # image processing
  im = Image.open('tmp.png')
 
  # Size of the image in pixels (size of original image): width, height = im.size
  left, top = 111, 67
  size_x, size_y = 783, 503
    
  cropped = im.crop((left, top, left+size_x, top+size_y))
  
  # cropped.show()
  cropped.save('../pictures/slideShow_auswertungen/graphDay.png')

  modDescription = [(str(testNum)+"."+str(subTest)), "getImg_range_24"] 
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])

  subTest = subTest + 1

  # set it back to old value
  driver.set_window_size(500, 700) # about mobile size, portrait style

  # subTest = getPage(driver, 'settings', testNum, subTest)
  # subTest = getPage(driver, 'statistic', testNum, subTest)

  return True
# end def
