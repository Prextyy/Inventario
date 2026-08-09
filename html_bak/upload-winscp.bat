@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo.
echo ====================================================
echo     WinSCP Upload - Sistema de Inventario
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
    echo Descargalo desde: https://winscp.net
    pause
    exit /b 1
)

echo [OK] WinSCP encontrado

set "SCRIPT=%TEMP%\winscp_upload_%RANDOM%.txt"

(
    echo option batch abort
    echo option confirm off
    echo open sftp://Prexty_share:inventario123@^@192.168.4.12:22
    echo cd /home/Prexty_share/public_html/inventario
    echo synchronize remote "F:\Inventario" . -delete
    echo ls -la
    echo exit
) > "%SCRIPT%"

echo.
echo ====================================================
echo Conectando a: 192.168.4.12
echo Usuario: Prexty_share
echo Origen: F:\Inventario
echo Destino: /home/Prexty_share/inventario
echo ====================================================
echo.
echo Por favor espera...
echo.

"%WINSCP_PATH%" /script="%SCRIPT%" /log="%TEMP%\winscp_log.txt"

if %errorlevel% equ 0 (
    echo.
    echo ====================================================
    echo [OK] SUBIDA COMPLETADA EXITOSAMENTE
    echo ====================================================
    echo.
) else (
    echo.
    echo ====================================================
    echo [ERROR] Fallo en la subida (Código: %errorlevel%)
    echo ====================================================
    echo.
    echo Posibles causas:
    echo - Servidor no disponible
    echo - Credenciales incorrectas
    echo - Permisos insuficientes
    echo.
    echo Log: %TEMP%\winscp_log.txt
    echo.
)

del /q "%SCRIPT%" >nul 2>&1

pause
