# Installation instruction

1. download node-v18.18.2-x64.msi (tested with this version)
1. install node into _div\nodejs\latest - directory
   * the whole 'latest' directory is afterwards write protected (on one installation). Security measures?
1. start a admin console and do: npm install tailwindcss flowbite chart.js
   * should result in something like "added 86 packages, and removed 250 packages"
1. do a repair of nodejs installation (ugly work around, _npm install_ somewhat removes too much)
1. runTailwindcss.bat (might result in a permission denied message for the copy command, do it manually if required)
   * should result in _rebuilding... Done_
1. --> generated CSS is working fine (in the range of 32 kB)
