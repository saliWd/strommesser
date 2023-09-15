import os
from re import sub

version = 'v1.0.1' # TODO: take from command line, needs to match a "vNumberPointNumberPointNumber"-pattern, otherwise end the script
project = 'display' # TODO: take from command line, must either be "display" or "measure"

def replace_content(dict_replace, target):
  """Based on dict, replaces key with the value on the target."""

  for check, replacer in list(dict_replace.items()):
    target = sub(check, replacer, target)

  return target

def changeVersionComment(version:str, inputFile:str, outputFile:str):
    dict_replace = { # currently only one to be replaced
        'xx_version_placeholder_xx': version
    }
    file_open = open(inputFile, 'r')
    file_read = file_open.read()
    file_open.close()
    new_file_open = open(outputFile, 'w')
    new_content = replace_content(dict_replace, file_read)
    new_file_open.write(new_content)
    new_file_open.close()

fileNamesIn  = ['boot_display.py', 'main_display.py', 'my_functions.py']
fileNamesOut = ['boot.py',         'main.py',         'my_functions.py']

# make sure the version directory exists
outFilePath = '../web/pico_w_ota/'+project+'/'+version
os.mkdir(outFilePath) # maybe: quit if it already exists?
print ("created the directory: "+outFilePath)



for i in range(0,len(fileNamesIn)):  
  changeVersionComment(
     version=version, 
     inputFile=fileNamesIn[i], 
     outputFile=outFilePath+'/'+fileNamesOut[i]
    )


print (">>> Done. Created "+str(len(fileNamesIn))+" files in the "+outFilePath+"-directory")
