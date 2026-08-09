@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo.
echo ====================================================
echo     Verificando Servidor - HestiaCP
echo ====================================================
echo.

set "WINSCP=C:\Program Files\WinSCP\WinSCP.com"
set "WINSCP_BACKUP=C:\Program Files (x86)\WinSCP\WinSCP.com"

if exist "%WINSCP%" (
    set "WINSCP_PATH=%WINSCP%"
) else if exist "%WINSCP_BACKUP%" (
    set "WINSCP_PATH=%WINSCP_BACKUP%"
) else (
    echo [ERROR] WinSCP no encontrado
    pause
    exit /b 1
)

set "SCRIPT=%TEMP%\winscp_check_%RANDOM%.txt"

(
    echo option batch abort
    echo option confirm off
    echo open sftp://Prexty_share:inventario123@^@192.168.4.12:22
    echo cd public_html
    echo pwd
    echo ls
    echo cd gastos.anihost.lat
    echo pwd
    echo ls
    echo exit
) > "%SCRIPT%"

echo Conectando al servidor para revisar estructura...
echo.

"%WINSCP_PATH%" /script="%SCRIPT%"

del /q "%SCRIPT%" >nul 2>&1

pause
