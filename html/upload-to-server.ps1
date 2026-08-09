#!/usr/bin/env powershell
# ============================================================================
# Script de Subida a Servidor - Sistema de Inventario
# ============================================================================
# Uso: .\upload-to-server.ps1
# Requiere: SSH/SCP disponible (Windows 10+ lo incluye)
# ============================================================================

# Configuración
$SERVER_IP = "192.168.4.12"
$SERVER_IP_BACKUP = "192.168.4.13"
$USERNAME = "Prexty_share"
$PASSWORD = "BZ2MJwQ(^Us+s2{t}"
$LOCAL_PATH = "f:\Inventario"
$REMOTE_PATH = "/home/$USERNAME/inventario"  # Ajusta según tu servidor

# Colores para terminal
$Colors = @{
    Success = "Green"
    Error   = "Red"
    Info    = "Cyan"
    Warning = "Yellow"
}

function Write-Log {
    param([string]$Message, [string]$Type = "Info")
    $Color = $Colors[$Type]
    Write-Host "[$((Get-Date).ToString('HH:mm:ss'))] $Message" -ForegroundColor $Color
}

function Test-Connection-SSH {
    param([string]$IP, [string]$User)
    Write-Log "Probando conexión a $IP..." "Info"
    
    # Prueba ping primero
    if (Test-Connection -ComputerName $IP -Count 1 -Quiet) {
        Write-Log "✓ Ping exitoso a $IP" "Success"
        return $true
    } else {
        Write-Log "✗ No se puede alcanzar $IP" "Error"
        return $false
    }
}

function Upload-Files {
    param(
        [string]$IP,
        [string]$User,
        [string]$Pass,
        [string]$LocalPath,
        [string]$RemotePath
    )
    
    Write-Log "Iniciando subida a $IP..." "Info"
    Write-Log "Origen: $LocalPath" "Info"
    Write-Log "Destino: $RemotePath" "Info"
    
    # Crear archivo temporal con contraseña para sftp
    $ScriptFile = "$env:TEMP\sftp_script_$((Get-Random)).txt"
    
    try {
        # Generar script de sftp
        # Nota: SCP es más simple que SFTP
        Write-Log "Preparando archivos para transferencia..." "Info"
        
        # Usar SCP para subir todo
        # Format: scp -r "source" user@host:destination
        $Command = @"
scp.exe -r "$LocalPath\*" "${User}@${IP}:${RemotePath}\" 2>&1
"@
        
        Write-Log "Ejecutando SCP..." "Info"
        $Output = cmd /c $Command
        
        if ($LASTEXITCODE -eq 0) {
            Write-Log "✓ Archivos subidos exitosamente" "Success"
            Write-Log $Output "Info"
            return $true
        } else {
            Write-Log "✗ Error en la subida" "Error"
            Write-Log $Output "Error"
            return $false
        }
    }
    catch {
        Write-Log "✗ Error: $_" "Error"
        return $false
    }
    finally {
        # Limpiar archivo temporal
        if (Test-Path $ScriptFile) {
            Remove-Item $ScriptFile -Force -ErrorAction SilentlyContinue
        }
    }
}

function Show-Menu {
    Clear-Host
    Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
    Write-Host "║          SUBIDA DE ARCHIVOS - SISTEMA INVENTARIO           ║" -ForegroundColor Cyan
    Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "1. Subir a IP Principal (192.168.4.12)" -ForegroundColor Green
    Write-Host "2. Subir a IP Alternativa (192.168.4.13)" -ForegroundColor Green
    Write-Host "3. Probar Conexión" -ForegroundColor Yellow
    Write-Host "4. Ver Archivos a Subir" -ForegroundColor Cyan
    Write-Host "5. Salir" -ForegroundColor Red
    Write-Host ""
}

# Script Principal
Write-Log "╔════════════════════════════════════════════════════════════╗" "Info"
Write-Log "║    SCRIPT DE SUBIDA - Sistema de Solicitudes v1.1.0        ║" "Info"
Write-Log "╚════════════════════════════════════════════════════════════╝" "Info"
Write-Host ""

# Verificar que la carpeta local existe
if (-not (Test-Path $LOCAL_PATH)) {
    Write-Log "✗ Error: No se encuentra la carpeta $LOCAL_PATH" "Error"
    exit 1
}

Write-Log "✓ Carpeta local encontrada: $LOCAL_PATH" "Success"

# Contar archivos
$FileCount = (Get-ChildItem -Path $LOCAL_PATH -Recurse -File).Count
Write-Log "Archivos a subir: $FileCount" "Info"
Write-Host ""

# Menu interactivo
do {
    Show-Menu
    $Choice = Read-Host "Selecciona opción (1-5)"
    
    switch ($Choice) {
        "1" {
            Write-Host ""
            if (Upload-Files -IP $SERVER_IP -User $USERNAME -Pass $PASSWORD -LocalPath $LOCAL_PATH -RemotePath $REMOTE_PATH) {
                Write-Host ""
                Write-Log "✓ Subida completada a $SERVER_IP" "Success"
            } else {
                Write-Host ""
                Write-Log "✗ Fallo en la subida" "Error"
            }
            Read-Host "Presiona ENTER para continuar"
        }
        
        "2" {
            Write-Host ""
            if (Upload-Files -IP $SERVER_IP_BACKUP -User $USERNAME -Pass $PASSWORD -LocalPath $LOCAL_PATH -RemotePath $REMOTE_PATH) {
                Write-Host ""
                Write-Log "✓ Subida completada a $SERVER_IP_BACKUP" "Success"
            } else {
                Write-Host ""
                Write-Log "✗ Fallo en la subida" "Error"
            }
            Read-Host "Presiona ENTER para continuar"
        }
        
        "3" {
            Write-Host ""
            Write-Log "Probando conexiones..." "Info"
            $Result1 = Test-Connection-SSH -IP $SERVER_IP -User $USERNAME
            Write-Host ""
            $Result2 = Test-Connection-SSH -IP $SERVER_IP_BACKUP -User $USERNAME
            Write-Host ""
            
            if ($Result1 -or $Result2) {
                Write-Log "✓ Al menos una conexión disponible" "Success"
            } else {
                Write-Log "✗ No se pudo conectar a ningún servidor" "Error"
            }
            Read-Host "Presiona ENTER para continuar"
        }
        
        "4" {
            Write-Host ""
            Write-Log "Archivos en $LOCAL_PATH:" "Info"
            Write-Host ""
            Get-ChildItem -Path $LOCAL_PATH -Recurse -File | ForEach-Object {
                Write-Host "  • $($_.FullName.Replace($LOCAL_PATH, ''))" -ForegroundColor Gray
            }
            Write-Host ""
            Read-Host "Presiona ENTER para continuar"
        }
        
        "5" {
            Write-Log "Saliendo..." "Info"
            exit 0
        }
        
        default {
            Write-Log "Opción inválida" "Warning"
            Read-Host "Presiona ENTER para continuar"
        }
    }
} while ($Choice -ne "5")
