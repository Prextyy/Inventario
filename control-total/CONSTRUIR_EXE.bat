@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo Generando ControlTotal.exe (requiere Python e internet la primera vez)...
py -3 -m pip install --upgrade pyinstaller || python -m pip install --upgrade pyinstaller
py -3 -m PyInstaller --noconfirm --onefile --name ControlTotal --add-data "static;static" app.py || python -m PyInstaller --noconfirm --onefile --name ControlTotal --add-data "static;static" app.py
echo.
echo Listo: el ejecutable quedo en la carpeta "dist". Copialo a tu USB o carpeta de trabajo.
pause
