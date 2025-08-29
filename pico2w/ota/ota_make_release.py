import os
from re import sub

version = 'v1.0.1' # TODO: take from command line, needs to match a "vNumberPointNumberPointNumber"-pattern, otherwise end the script

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

fileNames = ['boot.py',  'main.py', 'my_functions.py']

# make sure the version directory exists
outFilePath = '../../web/pico_w_ota/'+ version
if os.path.exists(outFilePath):
    print (">> warning. "+outFilePath+" already exists. Continuing anyway...") # files are just overwritten
else:
    os.mkdir(outFilePath) # maybe: quit if it already exists?
    print ("created the directory: "+outFilePath)


for i in range(0,len(fileNames)):  
  changeVersionComment(
     version=version, 
     inputFile=fileNames[i], 
     outputFile=outFilePath+'/'+fileNames[i]
    )

# need a file called 'version' one directory up. Containing only the version string
new_file_open = open('../../web/pico_w_ota/version', 'w')
new_file_open.write(version)
new_file_open.close()

print (">>> Done. Created "+str(len(fileNames))+" files in the "+outFilePath+"-directory and the corresponding version file")