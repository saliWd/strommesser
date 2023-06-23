# from selenium.webdriver.common.by import By
from PIL import Image
from functions import printOkOrNot, checkSiteTitleAndPrint

# 1. login
# 2. save different graphs as screenshots, do cropping and store images in ../pictures folder


def takeScreenshot(driver, testNum, url, imgName, subTest):
  import time
  driver.get(url)
  time.sleep(2) # wait until the graph did raise up (js effect)
  driver.save_screenshot('tmp.png')

  ### image processing
  im = Image.open('tmp.png')
 
  # size of the input image: width, height = im.size
  left, top = 111, 57 # one setting works for all graphs
  size_x, size_y = 783, 471 # size of the cropped image must be the same for all graphs, thus not a function param
  cropped = im.crop((left, top, left+size_x, top+size_y)) # cropped.show()
  cropped.save('../pictures/slideShow_auswertungen/'+imgName)

  modDescription = [(str(testNum)+"."+str(subTest)), "getImg_"+imgName] 
  printOkOrNot(ok=True, testNum=modDescription[0], text=modDescription[1])

  return (subTest + 1)
# end def


def getImg(driver, testNum):
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

  subTest = takeScreenshot(
    driver=driver,
    testNum=testNum,
    url='https://strommesser.ch/verbrauch/index.php?range=24',
    imgName='00_graphDay.png',
    subTest=subTest
  )
  subTest = takeScreenshot(
    driver=driver,
    testNum=testNum,
    url='https://strommesser.ch/verbrauch/statistic.php#anchorW',
    imgName='01_graphWeek.png',
    subTest=subTest
  )
  subTest = takeScreenshot(
    driver=driver,
    testNum=testNum,
    url='https://strommesser.ch/verbrauch/statistic.php?goBackW=1#anchorW',
    imgName='02_graphWeekLast.png',
    subTest=subTest
  )
  subTest = takeScreenshot(
    driver=driver,
    testNum=testNum,
    url='https://strommesser.ch/verbrauch/statistic.php#anchorM',
    imgName='03_graphMonth.png',
    subTest=subTest
  )

  # set it back to old value
  driver.set_window_size(500, 700) # about mobile size, portrait style

  return True
# end def
