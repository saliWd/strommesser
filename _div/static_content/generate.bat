@echo off
setlocal DisableDelayedExpansion
set INTEXTFILE=index.php.static
set OUTTEXTFILE="../../web/verbrauch/index.php.static.html"
set SEARCHTEXT=settings.php
set REPLACETEXT=settings.php.static.html
set OUTPUTLINE=
del %OUTTEXTFILE%
for /f "tokens=1,* delims=¶" %%A in ( '"type %INTEXTFILE%"') do (
    SET string=%%A
    setlocal EnableDelayedExpansion
    SET modified=!string:%SEARCHTEXT%=%REPLACETEXT%!

    >> %OUTTEXTFILE% echo(!modified!
    endlocal
)