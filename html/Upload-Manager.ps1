#!/usr/bin/env powershell
# ============================================================================
# Upload Manager - Sistema de Inventario
# Script Avanzado con Autenticación Automática
# ============================================================================

param(
    [switch]$Auto,           # Ejecutar sin menú interactivo
    [string]$IP = "192.168.4.12",  # IP del servidor
    [switch]$Backup,         # Usar IP backup
    [switch]$TestOnly        # Solo probar conexión
)

# Configuración
$Config = @{
    PrimaryIP    = "192.168.4.12"
    BackupIP     = "192.168.4.13"
    Username     = "Prexty_share"
    Password     = "BZ2MJwQ(^Us+s2{t}"
    LocalPath    = "f:\Inventario"
    RemotePath   = "/home/Prexty_share/inventario"
    SSHKey       = $null  # Opcional: ruta a clave SSH
}

# Colores
$Colors = @{
    Success = @{FG = "Green"; BG = "Black"}
    Error   = @{FG = "Red"; BG = "Black"}
    Info    = @{FG = "Cyan"; BG = "Black"}
    Warning = @{FG = "Yellow"; BG = "Black"}
    Debug   = @{FG = "Gray"; BG = "Black"}
}

function Write-ColorLine {
    param([string]$Message, [string]$Type = "Info", [bool]$NewLine = $true)
    
    $Color = $Colors[$Type]
    $Timestamp = Get-Date -Format "HH:mm:ss"
    
    if ($NewLine) {
        Write-Host "[$Timestamp] $Message" -ForegroundColor $Color.FG -BackgroundColor $Color.BG
    } else {
        Write-Host "[$Timestamp] $Message" -ForegroundColor $Color.FG -BackgroundColor $Color.BG -NoNewline
    }
}

function Invoke-RemoteCommand {
    param(
        [string]$IP,
        [pscredential]$Credential,
        [string]$Command
    )
    
    try {
        $SSH = New-SSHSession -ComputerName $IP -Credential $Credential -ErrorAction Stop
        $Result = Invoke-SSHCommand -SSHSession $SSH -Command $Command
        Remove-SSHSession -SSHSession $SSH | Out-Null
        return $Result
    }
    catch {
        return $null
    }
}

function Test-ServerConnection {
    param([string]$IP, [string]$Label = "Servidor")
    
    Write-ColorLine "Probando conexión a $Label ($IP)..." "Info" $false
    
    if (Test-Connection -ComputerName $IP -Count 1 -Quiet) {
        Write-ColorLine " ✓" "Success"
        return $true
    } else {
        Write-ColorLine " ✗" "Error"
        return $false
    }
}

function Upload-FilesWithSCP {
    param(
        [string]$IP,
        [string]$User,
        [string]$Pass,
        [string]$LocalPath,
        [string]$RemotePath,
        [string]$Label
    )
    
    Write-ColorLine "═════════════════════════════════════════════════════" "Info"
    Write-ColorLine "INICIANDO SUBIDA A: $Label ($IP)" "Info"
    Write-ColorLine "═════════════════════════════════════════════════════" "Info"
    Write-ColorLine "Local: $LocalPath" "Debug"
    Write-ColorLine "Remoto: ${User}@${IP}:${RemotePath}" "Debug"
    Write-Host ""
    
    $FileCount = @(Get-ChildItem -Path $LocalPath -Recurse -File).Count
    Write-ColorLine "Total de archivos: $FileCount" "Info"
    Write-Host ""
    
    try {
        Write-ColorLine "Ejecutando subida automática..." "Info"
        
        # Verificar si plink existe (PuTTY)
        $PlinkPath = (Get-Command plink -ErrorAction SilentlyContinue).Source
        $PSCPPath = (Get-Command pscp -ErrorAction SilentlyContinue).Source
        
        $StartTime = Get-Date
        
        if ($PSCPPath) {
            # Usar pscp con contraseña automática
            Write-ColorLine "✓ Usando PSCP (PuTTY) con contraseña automática" "Success"
            Write-Host ""
            
            & pscp -pw $Pass -r "$LocalPath\*" "${User}@${IP}:${RemotePath}/"
            
        } elseif ($PlinkPath) {
            # Usar plink para SCP con contraseña
            Write-ColorLine "✓ Usando PLINK (PuTTY) con contraseña automática" "Success"
            Write-Host ""
            
            & plink -pw $Pass "${User}@${IP}" "mkdir -p $RemotePath"
            dir /s /b "$LocalPath\*" | ForEach-Object {
                $file = $_
                $relative = $file.Replace($LocalPath, "").TrimStart('\')
                & plink -pw $Pass "${User}@${IP}" "scp -r '$file' '$RemotePath/$relative'"
            }
            
        } else {
            # Intentar SCP normal (puede pedir contraseña)
            Write-ColorLine "⚠ PSCP/PLINK no encontrados - usando SCP estándar" "Warning"
            Write-ColorLine "⚠ PUEDE PEDIR CONTRASEÑA MANUALMENTE" "Warning"
            Write-ColorLine "⚠ Si pide contraseña, copia: $Pass" "Warning"
            Write-Host ""
            
            & scp -o "StrictHostKeyChecking=no" -r "$LocalPath\*" "${User}@${IP}:${RemotePath}/"
        }
        
        $EndTime = Get-Date
        $Duration = $EndTime - $StartTime
        
        Write-Host ""
        
        Write-ColorLine "✓ SUBIDA COMPLETADA" "Success"
        Write-ColorLine "Tiempo: $($Duration.TotalSeconds) segundos" "Debug"
        Write-ColorLine "═════════════════════════════════════════════════════" "Success"
        return $true
    }
    catch {
        Write-ColorLine "✗ ERROR: $_" "Error"
        Write-ColorLine "═════════════════════════════════════════════════════" "Error"
        return $false
    }
}

function Show-Banner {
    Clear-Host
    Write-Host ""
    Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║        UPLOAD MANAGER - SISTEMA DE INVENTARIO v1.0         ║" -ForegroundColor Cyan
    Write-Host "║                                                            ║" -ForegroundColor Cyan
    Write-Host "║     Sistema de Solicitudes de Productos - Abril 2026       ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
}

function Show-Menu {
    Write-Host "┌────────────────────────────────────────────────────────────┐" -ForegroundColor Cyan
    Write-Host "│ OPCIONES                                                   │" -ForegroundColor Cyan
    Write-Host "├────────────────────────────────────────────────────────────┤" -ForegroundColor Cyan
    Write-Host "│                                                            │" -ForegroundColor Cyan
    Write-Host "│  1) Subir a IP Principal (192.168.4.12)                   │" -ForegroundColor Green
    Write-Host "│  2) Subir a IP Alternativa (192.168.4.13)                 │" -ForegroundColor Green
    Write-Host "│  3) Probar Conexión                                        │" -ForegroundColor Yellow
    Write-Host "│  4) Ver Archivos a Subir                                  │" -ForegroundColor Cyan
    Write-Host "│  5) Mostrar Configuración                                 │" -ForegroundColor Gray
    Write-Host "│  6) Salir                                                 │" -ForegroundColor Red
    Write-Host "│                                                            │" -ForegroundColor Cyan
    Write-Host "└────────────────────────────────────────────────────────────┘" -ForegroundColor Cyan
}

# MAIN
Show-Banner

# Verificar requisitos
Write-ColorLine "Verificando requisitos..." "Info"

if (-not (Test-Path $Config.LocalPath)) {
    Write-ColorLine "✗ ERROR: Carpeta no encontrada: $($Config.LocalPath)" "Error"
    exit 1
}

Write-ColorLine "✓ Carpeta local: $($Config.LocalPath)" "Success"

# Verificar SCP
$SCPTest = cmd /c "where scp" 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-ColorLine "✓ SCP disponible" "Success"
} else {
    Write-ColorLine "⚠ SCP no encontrado. Instala OpenSSH o Git Bash" "Warning"
}

Write-Host ""

# Modo automático
if ($Auto) {
    if ($TestOnly) {
        Write-ColorLine "Modo: Solo prueba de conexión" "Info"
        Test-ServerConnection -IP $Config.PrimaryIP -Label "Principal"
        Test-ServerConnection -IP $Config.BackupIP -Label "Alternativa"
    } else {
        $TargetIP = if ($Backup) { $Config.BackupIP } else { $Config.PrimaryIP }
        $Label = if ($Backup) { "Alternativa" } else { "Principal" }
        
        Upload-FilesWithSCP -IP $TargetIP -User $Config.Username `
                           -LocalPath $Config.LocalPath -RemotePath $Config.RemotePath `
                           -Label $Label
    }
    exit
}

# Menú interactivo
do {
    Show-Menu
    Write-Host ""
    $Choice = Read-Host "Selecciona opción"
    Write-Host ""
    
    switch ($Choice) {
        "1" {
            Upload-FilesWithSCP -IP $Config.PrimaryIP -User $Config.Username -Pass $Config.Password `
                               -LocalPath $Config.LocalPath -RemotePath $Config.RemotePath `
                               -Label "Principal (192.168.4.12)"
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "2" {
            Upload-FilesWithSCP -IP $Config.BackupIP -User $Config.Username -Pass $Config.Password `
                               -LocalPath $Config.LocalPath -RemotePath $Config.RemotePath `
                               -Label "Alternativa (192.168.4.13)"
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "3" {
            Write-ColorLine "Probando conexiones..." "Info"
            Write-Host ""
            $R1 = Test-ServerConnection -IP $Config.PrimaryIP -Label "Principal (192.168.4.12)"
            $R2 = Test-ServerConnection -IP $Config.BackupIP -Label "Alternativa (192.168.4.13)"
            Write-Host ""
            if ($R1 -or $R2) {
                Write-ColorLine "✓ Al menos una conexión disponible" "Success"
            } else {
                Write-ColorLine "✗ Ningún servidor accesible" "Error"
            }
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "4" {
            Write-ColorLine "Archivos en: $($Config.LocalPath)" "Info"
            Write-Host ""
            $Files = Get-ChildItem -Path $Config.LocalPath -Recurse -File
            $Files | ForEach-Object {
                $RelPath = $_.FullName.Replace($Config.LocalPath, "")
                Write-Host "  ✓ $RelPath" -ForegroundColor Gray
            }
            Write-Host ""
            Write-ColorLine "Total: $($Files.Count) archivos" "Success"
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "5" {
            Write-ColorLine "Configuración Actual:" "Info"
            Write-Host ""
            Write-Host "  IP Principal: $($Config.PrimaryIP)" -ForegroundColor Cyan
            Write-Host "  IP Alternativa: $($Config.BackupIP)" -ForegroundColor Cyan
            Write-Host "  Usuario: $($Config.Username)" -ForegroundColor Cyan
            Write-Host "  Ruta Local: $($Config.LocalPath)" -ForegroundColor Cyan
            Write-Host "  Ruta Remota: $($Config.RemotePath)" -ForegroundColor Cyan
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "6" {
            Write-ColorLine "¡Hasta luego!" "Info"
            exit 0
        }
        
        default {
            Write-ColorLine "Opción inválida" "Warning"
        }
    }
    
    Show-Banner
    
} while ($true)
