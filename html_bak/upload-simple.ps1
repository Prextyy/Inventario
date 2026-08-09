powershell -ExecutionPolicy Bypass -Command @"
# WinSCP Upload - Modo Automático

`$WinSCP = 'C:\Program Files\WinSCP\WinSCP.com'
`$Backup = 'C:\Program Files (x86)\WinSCP\WinSCP.com'

if (Test-Path `$WinSCP) {
    `$WinSCPPath = `$WinSCP
} elseif (Test-Path `$Backup) {
    `$WinSCPPath = `$Backup
} else {
    Write-Host 'ERROR: WinSCP no encontrado' -ForegroundColor Red
    exit 1
}

Write-Host 'WinSCP encontrado' -ForegroundColor Green

`$ScriptFile = `$env:TEMP + '\winscp_' + (Get-Random) + '.txt'

`$Script = @"
option batch abort
option confirm off
open sftp://Prexty_share:BZ2MJwQ(^Us+s2{t}@192.168.4.12:22
cd /home/Prexty_share/inventario
mkdir .
synchronize remote f:\Inventario /home/Prexty_share/inventario -delete
ls -la
exit
"@

`$Script | Out-File -FilePath `$ScriptFile -Encoding ASCII -Force

Write-Host ""
Write-Host "Subiendo archivos..." -ForegroundColor Cyan
Write-Host "De: f:\Inventario" -ForegroundColor Gray
Write-Host "A: 192.168.4.12:/home/Prexty_share/inventario" -ForegroundColor Gray
Write-Host ""

& `$WinSCPPath /script=`$ScriptFile /log=`$env:TEMP\winscp_log.txt

if (`$LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✓ SUBIDA COMPLETADA EXITOSAMENTE" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "ERROR: Código `$LASTEXITCODE" -ForegroundColor Red
}

Remove-Item `$ScriptFile -Force -ErrorAction SilentlyContinue
"@
