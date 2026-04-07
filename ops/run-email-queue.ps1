param(
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$queueScript = Join-Path $projectRoot 'cron\process_emails.php'

if (-not (Test-Path $PhpPath)) {
    throw "PHP executable not found at $PhpPath"
}

if (-not (Test-Path $queueScript)) {
    throw "Queue script not found at $queueScript"
}

& $PhpPath $queueScript