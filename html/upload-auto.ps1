#!/usr/bin/env powershell
# ============================================================================
# UPLOAD-AUTO.PS1 - Subida Automática Sin Preguntas
# ============================================================================
# Uso: .\upload-auto.ps1
# Características: Usa contraseña automáticamente, no pide nada
# ============================================================================

# Configuración
$IP = "192.168.4.12"          # Cambiar a .13 si es necesario
$USER = "Prexty_share"
$PASS = "BZ2MJwQ(^Us+s2{t}"
$LOCAL = "f:\Inventario"
$REMOTE = "/home/Prexty_share/inventario"

Clear-Host
Write-Host ""
Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║              SUBIDA AUTOMÁTICA DE ARCHIVOS                 ║" -ForegroundColor Cyan
Write-Host "║                                                            ║" -ForegroundColor Cyan
Write-Host "║            Sistema de Inventario v1.1.0                    ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

# Verificar carpeta
if (-not (Test-Path $LOCAL)) {
    Write-Host "[ERROR] Carpeta no encontrada: $LOCAL" -ForegroundColor Red
    exit 1
}

$FileCount = @(Get-ChildItem -Path $LOCAL -Recurse -File).Count
Write-Host "[OK] Carpeta encontrada" -ForegroundColor Green
Write-Host "[INFO] Archivos a subir: $FileCount" -ForegroundColor Cyan
Write-Host ""

# Mostrar configuración
Write-Host "Destino: $USER@$IP" -ForegroundColor Cyan
Write-Host "Ruta remota: $REMOTE" -ForegroundColor Cyan
Write-Host ""

# Verificar si existe PSCP (PuTTY)
$PSCPPath = (Get-Command pscp -ErrorAction SilentlyContinue).Source

if ($PSCPPath) {
    Write-Host "[*] PSCP encontrado - ejecutando con contraseña automática..." -ForegroundColor Yellow
    Write-Host ""
    
    # Ejecutar PSCP con contraseña
    & pscp -pw $PASS -r "$LOCAL\*" "${USER}@${IP}:${REMOTE}/"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Green
        Write-Host "║                   ✓ SUBIDA EXITOSA                         ║" -ForegroundColor Green
        Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Red
        Write-Host "║                   ✗ ERROR EN LA SUBIDA                     ║" -ForegroundColor Red
        Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Red
    }
} else {
    Write-Host "[*] Intentando con SCP estándar..." -ForegroundColor Yellow
    Write-Host "[INFO] Si pide contraseña, copia: $PASS" -ForegroundColor Yellow
    Write-Host ""
    
    & scp -o "StrictHostKeyChecking=no" -r "$LOCAL\*" "${USER}@${IP}:${REMOTE}/"
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Green
        Write-Host "║                   ✓ SUBIDA EXITOSA                         ║" -ForegroundColor Green
        Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Green
    } else {
        Write-Host ""
        Write-Host "╔════════════════════════════════════════════════════════════╗" -ForegroundColor Red
        Write-Host "║                   ✗ ERROR EN LA SUBIDA                     ║" -ForegroundColor Red
        Write-Host "║                                                            ║" -ForegroundColor Red
        Write-Host "║  SOLUCIÓN: Instala PuTTY para subida automatizada         ║" -ForegroundColor Red
        Write-Host "║  https://www.chiark.greenend.org.uk/~sgtatham/putty/      ║" -ForegroundColor Red
        Write-Host "╚════════════════════════════════════════════════════════════╝" -ForegroundColor Red
    }
}

Write-Host ""
