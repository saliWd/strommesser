from re import sub
from os.path import abspath, join, dirname

def replace_content(dict_replace, target):
  """Based on dict, replaces key with the value on the target."""

  for check, replacer in list(dict_replace.items()):
    target = sub(check, replacer, target)

  return target

dict_replace = {
  'settings.php': 'settings.php.static.html',
  'index.php"': 'index.php.6h.static.html"',
  'index.php\?range=1' : 'index.php.1h.static.html',
  'index.php\?range=6' : 'index.php.6h.static.html',
  'index.php\?range=24' : 'index.php.24h.static.html',
  'index.php\?range=25' : 'index.php.25h.static.html',
  '&reload=1' : '',
  '<body>':'<body><div style="width: 80%; top:7rem; min-height:3rem; padding:0 20px; text-align:center; font-size:larger; line-height:3rem; border-radius:3rem; box-sizing:border-box; color: rgb(25, 99, 132);border:2px solid rgb(25, 99, 132);  position:relative; display:block; background-color:rgba(255, 255, 255, 0.8); z-index:2; transform:rotate(-10deg);"><b>Demo-Account:</b> Daten sind nicht aktuell und Einstellungen werden nicht gespeichert.</div>'
}

file_names = ('index.php.1h.static','index.php.6h.static','index.php.24h.static','index.php.25h.static','settings.php.static')

for file_name in file_names:
    file = abspath(join(dirname(__file__), file_name))
    file_open = open(file, 'r')
    file_read = file_open.read()
    file_open.close()

    new_file = abspath(join(dirname(__file__), '../../web/verbrauch/'+file_name+'.html'))
    new_file_open = open(new_file, 'w')

    new_content = replace_content(dict_replace, file_read)

    new_file_open.write(new_content)
    new_file_open.close()
