<#
    Internship Tracking System - database integrity check.

    Runs mysqlcheck over every database. Exit code 0 = everything healthy,
    exit code 1 = at least one table needs attention (details in the log).

    Usage:
        powershell -ExecutionPolicy Bypass -File db-check.ps1
#>
[CmdletBinding()]
param(
    [string]$LogRoot = 'D:\maamCindy\db-backups'
)

$ErrorActionPreference = 'Continue'

$mysqlBin = 'C:\xampp\mysql\bin'
$logFile  = Join-Path $LogRoot 'integrity.log'

if (-not (Test-Path $LogRoot)) {
    New-Item -ItemType Directory -Force -Path $LogRoot | Out-Null
}

$output = & "$mysqlBin\mysqlcheck.exe" --user=root --host=127.0.0.1 --port=3306 --all-databases 2>&1
$problems = $output | Where-Object { $_ -match 'error|corrupt|warning|crashed' }

$header = '{0}  check: {1} problem line(s)' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), @($problems).Count
Add-Content -Path $logFile -Value $header -Encoding utf8

if (@($problems).Count -gt 0) {
    Add-Content -Path $logFile -Value ($problems -join "`n") -Encoding utf8
    $problems | ForEach-Object { Write-Output $_ }
    exit 1
}

exit 0
