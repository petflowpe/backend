param(
  [Parameter(Mandatory = $true)]
  [string]$LogFile,
  [Parameter(Mandatory = $false)]
  [string]$ApiBase = "http://127.0.0.1:8000/api"
)

$ErrorActionPreference = "Stop"

function Write-Section([string]$title) {
  Write-Host ""
  Write-Host ("=== {0} ===" -f $title)
}

function Try-Json($obj, [int]$depth = 10) {
  try { return ($obj | ConvertTo-Json -Depth $depth) } catch { return ($obj | Out-String) }
}

if (-not (Test-Path -LiteralPath $LogFile)) {
  throw "No existe LogFile: $LogFile"
}

$content = Get-Content -LiteralPath $LogFile -ErrorAction Stop

# Extraer admin desde el log
$adminLine = ($content | Select-String -Pattern '^\s*Admin:\s*(.+?)\s*/\s*(.+?)\s*$' | Select-Object -Last 1)
$adminEmail = if ($adminLine) { $adminLine.Matches[0].Groups[1].Value.Trim() } else { "admin@petflow.com" }
$adminPass  = if ($adminLine) { $adminLine.Matches[0].Groups[2].Value.Trim() } else { "PetFlow123456" }

# Token fallback desde el log
$tokenLine = ($content | Select-String -Pattern '^\s*PILOT_BEARER_TOKEN=(.+)\s*$' | Select-Object -Last 1)
$fallbackToken = if ($tokenLine) { $tokenLine.Matches[0].Groups[1].Value.Trim() } else { "" }

Write-Section "0) system/info (publico)"
try {
  $info = Invoke-RestMethod -Uri ("{0}/system/info" -f $ApiBase) -Method GET
  Write-Host (Try-Json $info 6)
} catch {
  Write-Host ("ERROR system/info: {0}" -f $_.Exception.Message)
}

Write-Section "Obteniendo token por login"
$token = ""
try {
  $body = @{ email = $adminEmail; password = $adminPass } | ConvertTo-Json -Depth 4
  $login = Invoke-RestMethod -Uri ("{0}/auth/login" -f $ApiBase) -Method POST -ContentType "application/json" -Body $body
  if ($login.access_token) { $token = [string]$login.access_token }
  if (-not $token) { throw "Login OK pero sin access_token" }
  Write-Host ("OK token por login para: {0}" -f $adminEmail)
} catch {
  Write-Host ("WARNING: login falló ({0}). Usando token del log..." -f $_.Exception.Message)
  $token = $fallbackToken
}

if (-not $token) {
  throw "No pude obtener token (ni login ni PILOT_BEARER_TOKEN en log)."
}

$headers = @{ Authorization = ("Bearer {0}" -f $token); Accept = "application/json" }

Write-Section "Verificando token (GET /api/v1/auth/me)"
try {
  $me = Invoke-RestMethod -Uri ("{0}/v1/auth/me" -f $ApiBase) -Method GET -Headers $headers
  Write-Host (Try-Json $me 6)
} catch {
  Write-Host ("ERROR auth/me: {0}" -f $_.Exception.Message)
}

Write-Section "1) GET vehicle-configurations/all (Bearer)"
try {
  $cfg = Invoke-RestMethod -Uri ("{0}/v1/vehicle-configurations/all" -f $ApiBase) -Method GET -Headers $headers
  Write-Host (Try-Json $cfg 12)
} catch {
  Write-Host ("ERROR getAll: {0}" -f $_.Exception.Message)
}

Write-Section "2) POST brands: ['TOYOTA','NISSAN']"
try {
  $postHeaders = @{ Authorization = ("Bearer {0}" -f $token); Accept = "application/json"; "Content-Type" = "application/json" }
  $body = @{ type = "brands"; items = @("TOYOTA","NISSAN") } | ConvertTo-Json -Depth 6
  $r = Invoke-RestMethod -Uri ("{0}/v1/vehicle-configurations" -f $ApiBase) -Method POST -Headers $postHeaders -Body $body
  Write-Host (Try-Json $r 6)
} catch {
  Write-Host ("ERROR post brands: {0}" -f $_.Exception.Message)
}

Write-Section "3) POST models_by_brand: {TOYOTA:[COROLLA]}"
try {
  $postHeaders = @{ Authorization = ("Bearer {0}" -f $token); Accept = "application/json"; "Content-Type" = "application/json" }
  $body = @{ type = "models_by_brand"; models_by_brand = @{ TOYOTA = @("COROLLA") } } | ConvertTo-Json -Depth 10
  $r = Invoke-RestMethod -Uri ("{0}/v1/vehicle-configurations" -f $ApiBase) -Method POST -Headers $postHeaders -Body $body
  Write-Host (Try-Json $r 6)
} catch {
  Write-Host ("ERROR post models: {0}" -f $_.Exception.Message)
}

Write-Section "4) GET vehicle-configurations/all (debe traer TOYOTA/NISSAN + COROLLA)"
try {
  $cfg = Invoke-RestMethod -Uri ("{0}/v1/vehicle-configurations/all" -f $ApiBase) -Method GET -Headers $headers
  Write-Host (Try-Json $cfg 12)
} catch {
  Write-Host ("ERROR getAll (final): {0}" -f $_.Exception.Message)
}

