import sys
import os
from re import sub, match

def is_valid_version(version):
    # Regex pattern: starts with 'v', followed by three groups of digits separated by dots
    pattern = r'^v\d+\.\d+\.\d+$'
    return match(pattern, version) is not None

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

fileNames = ['boot.py',  'main.py', 'function_def.py', 'class_def.py'] # my_config.py is not part of ota

if len(sys.argv) != 2:
    print('Usage: python ota_make_release.py <version>')
    sys.exit(1)

version_input = sys.argv[1]

if is_valid_version(version_input):
    version = version_input
else:
    print(f">> Error: '{version_input}' is NOT a valid version string, needs to be something like v1.2.3")
    print('...exiting program')
    sys.exit(1)

# make sure the version directory exists
outFilePath = '../../web/ota/'+ version
if os.path.exists(outFilePath):
    print (">> warning. "+outFilePath+" already exists. Continuing anyway...") # files are just overwritten
else:
    os.mkdir(outFilePath) # maybe: quit if it already exists?
    print ("created the directory: "+outFilePath)


for i in range(0,len(fileNames)):  
  changeVersionComment(
     version=version, 
     inputFile='../'+fileNames[i], 
     outputFile=outFilePath+'/'+fileNames[i]
    )

# need a file called 'version' one directory up. Containing only the version string
new_file_open = open('../../web/ota/version', 'w')
new_file_open.write(version)
new_file_open.close()

print (">>> Done. Created "+str(len(fileNames))+" files in the "+outFilePath+"-directory and the corresponding version file")