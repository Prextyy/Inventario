@echo off
REM ============================================================================
REM UPLOAD-AUTO.BAT - Subida Automática Sin Preguntas
REM ============================================================================
REM Uso: upload-auto.bat
REM Características: Automático, usa contraseña guardada, sin menú
REM ============================================================================

setlocal enabledelayedexpansion

title Subida Automática - Sistema de Inventario

set SERVER_IP=192.168.4.12
set USERNAME=Prexty_share
set PASSWORD=BZ2MJwQ(^Us+s2{t}
set LOCAL_PATH=f:\Inventario
set REMOTE_PATH=/home/Prexty_share/inventario

cls
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║          SUBIDA AUTOMÁTICA DE ARCHIVOS                    ║
echo ║                                                            ║
echo ║            Sistema de Inventario v1.1.0                    ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

REM Verificar que la carpeta existe
if not exist "%LOCAL_PATH%" (
    echo [ERROR] No se encontro la carpeta: %LOCAL_PATH%
    pause
    exit /b 1
)

REM Contar archivos
for /r "%LOCAL_PATH%" %%F in (*) do set /a FILE_COUNT+=1

echo [OK] Carpeta encontrada
echo [INFO] Archivos a subir: !FILE_COUNT!
echo.
echo Destino: %USERNAME%@%SERVER_IP%
echo Ruta remota: %REMOTE_PATH%
echo.
echo [*] Iniciando subida automática...
echo.

REM Intentar con PSCP primero (PuTTY)
where pscp >nul 2>&1
if !errorlevel! equ 0 (
    echo [*] PSCP encontrado - usando contraseña automática
    echo.
    
    pscp -pw "%PASSWORD%" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP%:%REMOTE_PATH%/
    
    if !errorlevel! equ 0 (
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║                   ✓ SUBIDA EXITOSA                         ║
        echo ╚════════════════════════════════════════════════════════════╝
    ) else (
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║                   ✗ ERROR EN LA SUBIDA                     ║
        echo ╚════════════════════════════════════════════════════════════╝
    )
) else (
    echo [*] PSCP no encontrado - intentando con SCP estándar
    echo [INFO] Si pide contraseña: %PASSWORD%
    echo.
    
    scp -o "StrictHostKeyChecking=no" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP%:%REMOTE_PATH%/
    
    if !errorlevel! equ 0 (
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║                   ✓ SUBIDA EXITOSA                         ║
        echo ╚════════════════════════════════════════════════════════════╝
    ) else (
        echo.
        echo ╔════════════════════════════════════════════════════════════╗
        echo ║                   ✗ ERROR EN LA SUBIDA                     ║
        echo ║                                                            ║
        echo ║  SOLUCIÓN: Instala PuTTY para subida automatizada         ║
        echo ║  https://www.chiark.greenend.org.uk/~sgtatham/putty/      ║
        echo ╚════════════════════════════════════════════════════════════╝
    )
)

echo.
pause
