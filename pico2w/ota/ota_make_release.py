import sys
import os
from re import sub, match
import shutil

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

FILE_NAMES        = ['boot.py',  'main.py', 'function_def.py', 'class_def.py'] # my_config.py is not part of ota
FILE_NAMES_BINARY = ['font.af', 'background.png'] # not replacing any content, just copying those to output dir
OUT_FILE_DIR = '../../web/ota/display/' # project is called display


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
outFilePath = OUT_FILE_DIR + version
if not os.path.exists(OUT_FILE_DIR): # no warning required here
    os.mkdir(OUT_FILE_DIR)
if os.path.exists(outFilePath):
    print (">> warning. "+outFilePath+" already exists. Continuing anyway, will overwrite files...")
else:
    os.mkdir(outFilePath)
    print ("created the directory: "+outFilePath)


for i in range(0,len(FILE_NAMES)):  
    changeVersionComment(
        version=version, 
        inputFile='../'+FILE_NAMES[i], 
        outputFile=outFilePath+'/'+FILE_NAMES[i]
    )
for i in range(0,len(FILE_NAMES_BINARY)):
    shutil.copyfile('../'+FILE_NAMES_BINARY[i], 
                    outFilePath+'/'+FILE_NAMES_BINARY[i])     

# need a file called 'version' one directory up. Containing only the version string
new_file_open = open(OUT_FILE_DIR+'version', 'w')
new_file_open.write(version)
new_file_open.close()

print (">>> Done. Created "+str(len(FILE_NAMES)+len(FILE_NAMES_BINARY))+" files in the "+outFilePath+"-directory and the corresponding version file")