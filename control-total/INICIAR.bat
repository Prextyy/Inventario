@echo off
chcp 65001 >nul
title Control Total
cd /d "%~dp0"
where py >nul 2>nul && (py -3 app.py %* & goto :fin)
where python >nul 2>nul && (python app.py %* & goto :fin)
echo.
echo  No se encontro Python en esta computadora.
echo  Opcion 1: usa ControlTotal.exe (no necesita Python).
echo  Opcion 2: instala Python desde https://www.python.org/downloads/
echo.
pause
:fin
