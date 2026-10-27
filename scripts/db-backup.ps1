<#
    Internship Tracking System - automatic MySQL backup.

    Dumps every application database plus the mysql system tables into a
    timestamped folder, then deletes the oldest automatic backups so the
    folder never grows without limit.

    Usage:
        powershell -ExecutionPolicy Bypass -File db-backup.ps1
        powershell -ExecutionPolicy Bypass -File db-backup.ps1 -Keep 60
#>
[CmdletBinding()]
param(
    [string]$BackupRoot = 'D:\maamCindy\db-backups',
    [int]$Keep = 30
)

$ErrorActionPreference = 'Stop'

$mysqlBin = 'C:\xampp\mysql\bin'
$stamp    = Get-Date -Format 'yyyyMMdd-HHmmss'
$dir      = Join-Path $BackupRoot "auto-$stamp"
$logFile  = Join-Path $BackupRoot 'backup.log'

function Write-Log([string]$Message) {
    $line = "{0}  {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $logFile -Value $line -Encoding utf8
}

if (-not (Test-Path $BackupRoot)) {
    New-Item -ItemType Directory -Force -Path $BackupRoot | Out-Null
}

try {
    # Fail fast when the server is not running.
    & "$mysqlBin\mysqladmin.exe" --user=root --host=127.0.0.1 --port=3306 ping 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'MySQL is not running.' }

    New-Item -ItemType Directory -Force -Path $dir | Out-Null

    & "$mysqlBin\mysqldump.exe" `
        --user=root --host=127.0.0.1 --port=3306 `
        --single-transaction --quick --routines --triggers --events `
        --databases internship_tracking_system internship_tracking_system_erd mysql `
        --ignore-table=mysql.general_log --ignore-table=mysql.slow_log `
        --result-file="$dir\full-backup.sql"

    if ($LASTEXITCODE -ne 0) { throw "mysqldump failed (exit $LASTEXITCODE)." }

    $sizeMb = [math]::Round((Get-Item "$dir\full-backup.sql").Length / 1MB, 2)
    Write-Log "OK   $dir ($sizeMb MB)"

    # Retention: keep only the newest $Keep automatic backups.
    $old = Get-ChildItem $BackupRoot -Directory -Filter 'auto-*' |
        Sort-Object Name -Descending |
        Select-Object -Skip $Keep
    foreach ($item in $old) {
        Remove-Item $item.FullName -Recurse -Force
        Write-Log "PRUNE $($item.Name)"
    }

    exit 0
}
catch {
    Write-Log "FAIL $($_.Exception.Message)"
    exit 1
}
