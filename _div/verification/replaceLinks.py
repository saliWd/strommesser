from re import sub
from os.path import abspath, join, dirname

def replace_content(dict_replace, target):
  """Based on dict, replaces key with the value on the target."""

  for check, replacer in list(dict_replace.items()):
    target = sub(check, replacer, target)

  return target

dict_replace = {
  'login.php"': 'static.login.html"',
  'login.php\\?do=.?' : 'static.login.html',
  'logout.php' : 'static.logout.html',
  'status.php"': 'static.status.html"',
  'status_loopCount.php"': 'static.status_loopCount.html"',
  'now.php"': 'static.now.html"',
  'settings.php"': 'static.settings.html"',
  'settings.php\\?do=.?': 'static.settings.html',
  'statistic.php"': 'static.statistic.html"',
  'statistic.php\\?weeksPast=1': 'static.statistic.html',
  'index.php"': 'static.index.1.html"',
  'index.php\\?range=1' : 'static.index.1.html',
  'index.php\\?range=7' : 'static.index.7.html',
  'index.php\\?range=30' : 'static.index.30.html',
  'index.php\\?range=365' : 'static.index.365.html',
  '<body>':'<body><div style="width: 80%; top:7rem; min-height:3rem; padding:0 20px; text-align:center; font-size:larger; line-height:3rem; border-radius:3rem; box-sizing:border-box; color: rgb(25, 99, 132);border:2px solid rgb(25, 99, 132);  position:relative; display:block; background-color:rgba(255, 255, 255, 0.8); z-index:2; transform:rotate(-10deg);"><b>Demo-Account:</b> Daten sind nicht aktuell und Einstellungen werden nicht gespeichert.</div>'
}

file_names = ('static.index.1','static.index.7','static.index.30','static.index.365',
'static.settings',
'static.statistic',
'static.login',
'static.logout',
'static.status',
'static.status_loopCount',
'static.now')

fileCounter = 0

for file_name in file_names:
    file = abspath(join(dirname(__file__), 'staticHtml/'+file_name))
    file_open = open(file, 'r')
    file_read = file_open.read()
    file_open.close()

    new_file = abspath(join(dirname(__file__), '../../web/verbrauch/'+file_name+'.html'))
    new_file_open = open(new_file, 'w')

    new_content = replace_content(dict_replace, file_read)

    new_file_open.write(new_content)
    new_file_open.close()
    fileCounter = fileCounter + 1

print("generated "+str(fileCounter)+" files in ../../web/verbrauch/ directory.")    
