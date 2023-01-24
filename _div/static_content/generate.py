from re import sub
from os.path import abspath, join, dirname

def replace_content(dict_replace, target):
  """Based on dict, replaces key with the value on the target."""

  for check, replacer in list(dict_replace.items()):
    target = sub(check, replacer, target)

  return target

dict_replace = {
  'settings.php': 'settings.php.static.html',
  'index.php': 'index.php.static.html'
}

file_names = ('index.php.static','settings.php.static')

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
