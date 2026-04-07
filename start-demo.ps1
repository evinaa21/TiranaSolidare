# ============================================================
#  TiranaSolidare — Demo Startup Script
#  Run BEFORE the jury presentation.
#  Usage: .\start-demo.ps1
#
#  What it does:
#   1. Starts Mailpit  (fake email inbox at http://localhost:8025)
#   2. Starts ngrok    (if NGROK_TOKEN is set in .env)
#      OR uses LAN IP  (if phone is on same WiFi as laptop)
#   3. Updates APP_URL in .env so email links work on phones
#   4. Prints all URLs you need to open
# ============================================================

Set-Location $PSScriptRoot

# --- Read .env helper ----------------------------------------
function Read-EnvFile {
    $dict = @{}
    if (Test-Path ".env") {
        Get-Content ".env" | ForEach-Object {
            $line = $_.Trim()
            if ($line -and ($line[0] -ne '#') -and ($line -match '=')) {
                $parts = $line -split '=', 2
                $dict[$parts[0].Trim()] = $parts[1].Trim()
            }
        }
    }
    return $dict
}

# --- Stop old instances --------------------------------------
Write-Host "`n[1/4] Cleaning up old processes..." -ForegroundColor Cyan
Get-Process -Name "mailpit"  -ErrorAction SilentlyContinue | Stop-Process -Force
Get-Process -Name "ngrok"    -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 1

# --- Start Mailpit -------------------------------------------
Write-Host "[2/4] Starting Mailpit (SMTP:1025, Inbox:8025)..." -ForegroundColor Cyan
$mailpitExe = Join-Path $PSScriptRoot "mailpit\mailpit.exe"
if (-not (Test-Path $mailpitExe)) {
    Write-Host "  WARNING: mailpit.exe not found at $mailpitExe" -ForegroundColor Yellow
} else {
    Start-Process -FilePath $mailpitExe -ArgumentList "--smtp", "0.0.0.0:1025", "--listen", "0.0.0.0:8025" -WindowStyle Minimized
    Start-Sleep -Seconds 1
    Write-Host "  Mailpit running." -ForegroundColor Green
}

# --- Determine public URL ------------------------------------
Write-Host "[3/4] Configuring public URL..." -ForegroundColor Cyan

$envVars     = Read-EnvFile
$ngrokToken  = $envVars['NGROK_TOKEN']
$publicAppUrl = $null

if ($ngrokToken) {
    # --- ngrok mode (phone on any network) ---
    Write-Host "  NGROK_TOKEN found - starting ngrok tunnel..." -ForegroundColor DarkCyan
    $ngrokExe = Join-Path $PSScriptRoot "ngrok\ngrok.exe"
    & $ngrokExe config add-authtoken $ngrokToken --config "$PSScriptRoot\ngrok\ngrok.yml" 2>$null
    Start-Process -FilePath $ngrokExe -ArgumentList "http", "80", "--config", "$PSScriptRoot\ngrok\ngrok.yml", "--log", "stdout" -WindowStyle Minimized
    Write-Host "  Waiting for ngrok tunnel..." -ForegroundColor DarkCyan
    $retries = 0
    while ($retries -lt 10) {
        Start-Sleep -Seconds 2
        try {
            $tunnels = (Invoke-RestMethod "http://localhost:4040/api/tunnels" -ErrorAction Stop).tunnels
            $httpsTunnel = $tunnels | Where-Object { $_.proto -eq "https" } | Select-Object -First 1
            if ($httpsTunnel) {
                $publicAppUrl = $httpsTunnel.public_url + "/TiranaSolidare"
                break
            }
        } catch {}
        $retries++
    }
    if (-not $publicAppUrl) {
        Write-Host "  WARNING: ngrok tunnel timed out. Falling back to LAN IP." -ForegroundColor Yellow
    }
}

if (-not $publicAppUrl) {
    # --- LAN IP mode (phone on same WiFi as laptop) ---
    $lanIP = (Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -notmatch '^(127\.|169\.254\.)' -and $_.PrefixOrigin -ne 'WellKnown' } |
        Sort-Object -Property PrefixLength |
        Select-Object -First 1).IPAddress

    if ($lanIP) {
        $publicAppUrl = "http://$lanIP/TiranaSolidare"
        Write-Host "  Using LAN IP: $lanIP (phone must be on same WiFi)" -ForegroundColor DarkCyan
    } else {
        $publicAppUrl = "http://localhost/TiranaSolidare"
        Write-Host "  Could not detect LAN IP - using localhost." -ForegroundColor Yellow
    }
}

# --- Update APP_URL in .env ----------------------------------
Write-Host "[4/4] Updating APP_URL in .env to: $publicAppUrl" -ForegroundColor Cyan
$envContent = Get-Content ".env" -Raw
$envContent = $envContent -replace '(?m)^APP_URL=.*', "APP_URL=$publicAppUrl"
Set-Content ".env" $envContent -NoNewline
Write-Host "  .env updated." -ForegroundColor Green

# --- Print summary -------------------------------------------
Write-Host ""
Write-Host "=========================================" -ForegroundColor Green
Write-Host "   DEMO IS READY" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  App (Tab A - Admin)  : $publicAppUrl" -ForegroundColor White
Write-Host "  App (Tab B - Elira)  : $publicAppUrl" -ForegroundColor White
Write-Host "  Email Inbox (Mailpit): http://localhost:8025" -ForegroundColor White
if ($ngrokToken) {
    Write-Host "  ngrok dashboard      : http://localhost:4040" -ForegroundColor White
}
Write-Host ""
Write-Host "  Admin login : demo.admin@tiranasolidare.local / Demo123!" -ForegroundColor DarkGray
Write-Host "  User login  : demo.elira@tiranasolidare.local / Demo123!" -ForegroundColor DarkGray
Write-Host ""
Write-Host "  PHONE ACCESS:" -ForegroundColor Yellow
if ($ngrokToken) {
    Write-Host "  Open on any phone: $publicAppUrl" -ForegroundColor Yellow
} else {
    Write-Host "  Connect phone to the same WiFi as this laptop" -ForegroundColor Yellow
    Write-Host "  Then open: $publicAppUrl" -ForegroundColor Yellow
    Write-Host "  For phone on different network: add NGROK_TOKEN=<your_token> to .env" -ForegroundColor DarkGray
    Write-Host "  (Free at https://ngrok.com - takes 2 minutes to set up)" -ForegroundColor DarkGray
}
Write-Host ""
Write-Host "  EMAIL SETUP:" -ForegroundColor Cyan
Write-Host "  - ALL emails    -> http://localhost:8025  (Mailpit)" -ForegroundColor Cyan
Write-Host "  - Real addresses -> also forwarded to Gmail (mailservice205@gmail.com)" -ForegroundColor Cyan
Write-Host ""
