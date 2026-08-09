@echo off
REM ============================================================================
REM Script de Subida a Servidor - Sistema de Inventario (Batch)
REM ============================================================================
REM Uso: upload-to-server.bat
REM Requiere: SCP disponible (Windows 10+ lo incluye)
REM ============================================================================

setlocal enabledelayedexpansion

title Subida de Archivos - Sistema de Inventario

set SERVER_IP=192.168.4.12
set SERVER_IP_BACKUP=192.168.4.13
set USERNAME=Prexty_share
set LOCAL_PATH=f:\Inventario
set REMOTE_PATH=/home/%USERNAME%/inventario

cls
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║          SUBIDA DE ARCHIVOS - SISTEMA INVENTARIO           ║
echo ║                 Script Batch v1.0                          ║
echo ╚════════════════════════════════════════════════════════════╝
echo.

REM Verificar que la carpeta existe
if not exist "%LOCAL_PATH%" (
    echo [ERROR] No se encontro la carpeta: %LOCAL_PATH%
    pause
    exit /b 1
)

echo [OK] Carpeta encontrada: %LOCAL_PATH%
echo.

:menu
cls
echo.
echo ╔════════════════════════════════════════════════════════════╗
echo ║          OPCIONES DE SUBIDA                                ║
echo ╚════════════════════════════════════════════════════════════╝
echo.
echo 1. Subir a IP Principal (%SERVER_IP%)
echo 2. Subir a IP Alternativa (%SERVER_IP_BACKUP%)
echo 3. Probar Conexion
echo 4. Ver Archivos
echo 5. Salir
echo.
set /p choice="Selecciona opcion (1-5): "

if "%choice%"=="1" goto upload1
if "%choice%"=="2" goto upload2
if "%choice%"=="3" goto test_conn
if "%choice%"=="4" goto list_files
if "%choice%"=="5" goto end
goto menu

:upload1
echo.
echo [*] Configurando subida a %SERVER_IP%...
echo [*] Origen: %LOCAL_PATH%
echo [*] Destino: %REMOTE_PATH%/
echo.
echo [*] Subiendo archivos (esto puede tomar unos minutos)...
echo.

REM Crear carpeta remota primero
REM scp.exe -o "StrictHostKeyChecking=no" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP%:%REMOTE_PATH%/ 2>nul

REM Intentar con plink de PuTTY (màs automatizado)
where plink >nul 2>&1
if !errorlevel! equ 0 (
    echo [*] Usando plink de PuTTY para subida automatizada...
    REM plink con SSH y luego pscp
    pscp -pw "BZ2MJwQ(^Us+s2{t}" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP%:%REMOTE_PATH%/
) else (
    echo [*] Usando SCP (puede pedir contraseña)...
    echo [INFO] Contraseña: BZ2MJwQ(^Us+s2{t}
    scp -o "StrictHostKeyChecking=no" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP%:%REMOTE_PATH%/
)

if !errorlevel! equ 0 (
    echo.
    echo [OK] Archivos subidos exitosamente a %SERVER_IP%
) else (
    echo.
    echo [ERROR] Fallo en la subida
    echo [INFO] Si pide contraseña, usa: BZ2MJwQ(^Us+s2{t}
)
echo.
pause
goto menu

:upload2
echo.
echo [*] Configurando subida a %SERVER_IP_BACKUP%...
echo [*] Origen: %LOCAL_PATH%
echo [*] Destino: %REMOTE_PATH%/
echo.
echo [*] Subiendo archivos (esto puede tomar unos minutos)...
echo.

REM Intentar con plink de PuTTY (más automatizado)
where plink >nul 2>&1
if !errorlevel! equ 0 (
    echo [*] Usando plink de PuTTY para subida automatizada...
    pscp -pw "BZ2MJwQ(^Us+s2{t}" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP_BACKUP%:%REMOTE_PATH%/
) else (
    echo [*] Usando SCP (puede pedir contraseña)...
    echo [INFO] Contraseña: BZ2MJwQ(^Us+s2{t}
    scp -o "StrictHostKeyChecking=no" -r "%LOCAL_PATH%\*" %USERNAME%@%SERVER_IP_BACKUP%:%REMOTE_PATH%/
)

if !errorlevel! equ 0 (
    echo.
    echo [OK] Archivos subidos exitosamente a %SERVER_IP_BACKUP%
) else (
    echo.
    echo [ERROR] Fallo en la subida
    echo [INFO] Si pide contraseña, usa: BZ2MJwQ(^Us+s2{t}
)
echo.
pause
goto menu

:test_conn
echo.
echo [*] Probando conexion a %SERVER_IP%...
ping -n 1 %SERVER_IP% >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Conexion exitosa a %SERVER_IP%
) else (
    echo [ERROR] No se puede alcanzar %SERVER_IP%
)
echo.
echo [*] Probando conexion a %SERVER_IP_BACKUP%...
ping -n 1 %SERVER_IP_BACKUP% >nul 2>&1
if !errorlevel! equ 0 (
    echo [OK] Conexion exitosa a %SERVER_IP_BACKUP%
) else (
    echo [ERROR] No se puede alcanzar %SERVER_IP_BACKUP%
)
echo.
pause
goto menu

:list_files
echo.
echo [*] Archivos en: %LOCAL_PATH%
echo.
dir /s /b "%LOCAL_PATH%"
echo.
pause
goto menu

:end
echo.
echo [*] Saliendo...
exit /b 0
