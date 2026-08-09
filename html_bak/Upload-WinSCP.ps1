#!/usr/bin/env powershell
# ============================================================================
# WinSCP Upload Manager - HestiaCP
# ============================================================================
# Automatiza la subida usando WinSCP
# ============================================================================

$Config = @{
    PrimaryIP    = "192.168.4.12"
    BackupIP     = "192.168.4.13"
    Username     = "Prexty_share"
    Password     = "BZ2MJwQ(^Us+s2{t}"
    LocalPath    = "f:\Inventario"
    RemotePath   = "/home/Prexty_share/inventario"
    Port         = 22
}

function Test-WinSCP {
    Write-Host "[*] Buscando WinSCP..." -ForegroundColor Cyan
    
    $WinSCPLocations = @(
        "C:\Program Files\WinSCP\WinSCP.com",
        "C:\Program Files (x86)\WinSCP\WinSCP.com",
        "$env:PROGRAMFILES\WinSCP\WinSCP.com",
        "$env:ProgramFiles(x86)\WinSCP\WinSCP.com"
    )
    
    foreach ($location in $WinSCPLocations) {
        if (Test-Path $location) {
            Write-Host "[OK] WinSCP encontrado en: $location" -ForegroundColor Green
            return $location
        }
    }
    
    Write-Host "[ERROR] WinSCP no encontrado" -ForegroundColor Red
    Write-Host ""
    Write-Host "Descargar WinSCP desde:" -ForegroundColor Yellow
    Write-Host "https://winscp.net/eng/download.php" -ForegroundColor Cyan
    Write-Host ""
    return $null
}

function Upload-WithWinSCP {
    param(
        [string]$WinSCPPath,
        [string]$IP,
        [string]$User,
        [string]$Pass,
        [string]$LocalPath,
        [string]$RemotePath,
        [string]$Label
    )
    
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║ INICIANDO SUBIDA CON WINSCP                           ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Servidor: $Label ($IP)" -ForegroundColor Cyan
    Write-Host "Usuario: $User" -ForegroundColor Cyan
    Write-Host "Origen: $LocalPath" -ForegroundColor Cyan
    Write-Host "Destino: $RemotePath" -ForegroundColor Cyan
    Write-Host ""
    
    # Crear script de WinSCP
    $ScriptFile = "$env:TEMP\winscp_script_$(Get-Random).txt"
    
    # Contenido del script WinSCP
    $ScriptContent = @"
option batch abort
option confirm off
open sftp://$User`:$Pass@$IP`:22

# Cambiar a directorio remoto
cd $RemotePath

# Crear directorio si no existe
mkdir .

# Subir archivos
synchronize remote "$LocalPath" "$RemotePath" -delete

# Mostrar resultado
ls -la

exit
"@
    
    try {
        # Guardar script
        $ScriptContent | Out-File -FilePath $ScriptFile -Encoding ASCII -Force
        
        Write-Host "[*] Ejecutando WinSCP..." -ForegroundColor Yellow
        
        # Ejecutar WinSCP con el script
        $StartTime = Get-Date
        
        $Output = & $WinSCPPath /script=$ScriptFile /log="$env:TEMP\winscp_log_$(Get-Random).txt" 2>&1
        
        $EndTime = Get-Date
        $Duration = $EndTime - $StartTime
        
        # Verificar resultado
        if ($LASTEXITCODE -eq 0) {
            Write-Host ""
            Write-Host "✓ SUBIDA COMPLETADA EXITOSAMENTE" -ForegroundColor Green
            Write-Host "Tiempo: $($Duration.TotalSeconds) segundos" -ForegroundColor Gray
            Write-Host ""
            Write-Host $Output -ForegroundColor Gray
            return $true
        } else {
            Write-Host ""
            Write-Host "✗ ERROR EN LA SUBIDA" -ForegroundColor Red
            Write-Host "Código: $LASTEXITCODE" -ForegroundColor Red
            Write-Host ""
            Write-Host $Output -ForegroundColor Gray
            return $false
        }
    }
    catch {
        Write-Host "✗ EXCEPCIÓN: $_" -ForegroundColor Red
        return $false
    }
    finally {
        # Limpiar archivos temporales
        if (Test-Path $ScriptFile) {
            Remove-Item $ScriptFile -Force -ErrorAction SilentlyContinue
        }
    }
}

function Open-WinSCPGUI {
    param([string]$IP, [string]$User, [string]$Pass)
    
    Write-Host "[*] Abriendo WinSCP GUI..." -ForegroundColor Cyan
    
    $WinSCPExe = "C:\Program Files\WinSCP\WinSCP.exe"
    if (-not (Test-Path $WinSCPExe)) {
        $WinSCPExe = "C:\Program Files (x86)\WinSCP\WinSCP.exe"
    }
    
    if (Test-Path $WinSCPExe) {
        # Formato: WinSCP.exe sftp://user:password@host/path
        $URL = "sftp://${User}:${Pass}@${IP}/"
        & $WinSCPExe $URL
    } else {
        Write-Host "✗ No se pudo encontrar WinSCP.exe" -ForegroundColor Red
    }
}

# MAIN
Clear-Host
Write-Host ""
Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║           WINSCP UPLOAD MANAGER - HESTIACP                ║" -ForegroundColor Cyan
Write-Host "║                                                            ║" -ForegroundColor Cyan
Write-Host "║        Sistema de Solicitudes de Productos v1.1.0          ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Verificar WinSCP
$WinSCPPath = Test-WinSCP

if (-not $WinSCPPath) {
    Write-Host ""
    Write-Host "[!] WinSCP no está instalado" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Opciones:" -ForegroundColor Cyan
    Write-Host "1. Descargar e instalar WinSCP desde https://winscp.net" -ForegroundColor Gray
    Write-Host "2. Luego ejecutar este script nuevamente" -ForegroundColor Gray
    Write-Host ""
    
    $Opcion = Read-Host "¿Descargar WinSCP ahora? (S/N)"
    if ($Opcion -eq "S" -or $Opcion -eq "s") {
        Start-Process "https://winscp.net/eng/download.php"
        Write-Host "Se abrió el navegador. Instala WinSCP y luego ejecuta este script nuevamente." -ForegroundColor Yellow
    }
    exit
}

# Verificar carpeta local
if (-not (Test-Path $Config.LocalPath)) {
    Write-Host "✗ Error: No se encontró $($Config.LocalPath)" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Carpeta local: $($Config.LocalPath)" -ForegroundColor Green

$FileCount = @(Get-ChildItem -Path $Config.LocalPath -Recurse -File).Count
Write-Host "✓ Archivos a subir: $FileCount" -ForegroundColor Green
Write-Host ""

# Menú
do {
    Write-Host "┌────────────────────────────────────────────────────────────┐" -ForegroundColor Cyan
    Write-Host "│ OPCIONES                                                   │" -ForegroundColor Cyan
    Write-Host "├────────────────────────────────────────────────────────────┤" -ForegroundColor Cyan
    Write-Host "│                                                            │" -ForegroundColor Cyan
    Write-Host "│ 1) Subir a IP Principal (192.168.4.12) - AUTOMÁTICO       │" -ForegroundColor Green
    Write-Host "│ 2) Subir a IP Alternativa (192.168.4.13) - AUTOMÁTICO     │" -ForegroundColor Green
    Write-Host "│ 3) Abrir GUI WinSCP - MANUAL (192.168.4.12)              │" -ForegroundColor Green
    Write-Host "│ 4) Abrir GUI WinSCP - MANUAL (192.168.4.13)              │" -ForegroundColor Green
    Write-Host "│ 5) Ver Archivos                                           │" -ForegroundColor Cyan
    Write-Host "│ 6) Salir                                                  │" -ForegroundColor Red
    Write-Host "│                                                            │" -ForegroundColor Cyan
    Write-Host "└────────────────────────────────────────────────────────────┘" -ForegroundColor Cyan
    Write-Host ""
    
    $Choice = Read-Host "Selecciona opción (1-6)"
    
    switch ($Choice) {
        "1" {
            Upload-WithWinSCP -WinSCPPath $WinSCPPath `
                             -IP $Config.PrimaryIP `
                             -User $Config.Username `
                             -Pass $Config.Password `
                             -LocalPath $Config.LocalPath `
                             -RemotePath $Config.RemotePath `
                             -Label "Principal (192.168.4.12)"
            Read-Host "Presiona ENTER para continuar"
        }
        
        "2" {
            Upload-WithWinSCP -WinSCPPath $WinSCPPath `
                             -IP $Config.BackupIP `
                             -User $Config.Username `
                             -Pass $Config.Password `
                             -LocalPath $Config.LocalPath `
                             -RemotePath $Config.RemotePath `
                             -Label "Alternativa (192.168.4.13)"
            Read-Host "Presiona ENTER para continuar"
        }
        
        "3" {
            Open-WinSCPGUI -IP $Config.PrimaryIP -User $Config.Username -Pass $Config.Password
            Write-Host "[*] WinSCP GUI abierto en nueva ventana" -ForegroundColor Yellow
            Read-Host "Presiona ENTER cuando termines"
        }
        
        "4" {
            Open-WinSCPGUI -IP $Config.BackupIP -User $Config.Username -Pass $Config.Password
            Write-Host "[*] WinSCP GUI abierto en nueva ventana" -ForegroundColor Yellow
            Read-Host "Presiona ENTER cuando termines"
        }
        
        "5" {
            Write-Host ""
            Write-Host "Archivos en: $($Config.LocalPath)" -ForegroundColor Cyan
            Write-Host ""
            Get-ChildItem -Path $Config.LocalPath -Recurse -File | ForEach-Object {
                $RelPath = $_.FullName.Replace($Config.LocalPath, "")
                Write-Host "  ✓ $RelPath" -ForegroundColor Gray
            }
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "6" {
            Write-Host "¡Hasta luego!" -ForegroundColor Green
            exit 0
        }
        
        default {
            Write-Host "Opción inválida" -ForegroundColor Red
        }
    }
    
    Clear-Host
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║           WINSCP UPLOAD MANAGER - HESTIACP                ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    
} while ($true)
