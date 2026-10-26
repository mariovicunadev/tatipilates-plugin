# Pre-release check Tati Pilates (version Windows de pre-release-check.sh).
# Uso: .\pre-release-check.ps1 [-Yes] [-TestedWp 7.1] [-NoZip]
param([switch]$Yes, [string]$TestedWp = "", [switch]$NoZip)

$ErrorActionPreference = "Stop"
$Root = $PSScriptRoot
Set-Location $Root

# PHP: TP_PHP, PHP de Local (si existe) o php del PATH.
$php = $env:TP_PHP
if (-not $php) {
  $local = Get-ChildItem "$env:APPDATA\Local\lightning-services\php-*\bin\win64\php.exe" -ErrorAction SilentlyContinue | Select-Object -Last 1
  if ($local) { $php = $local.FullName }
}
if (-not $php) { $php = (Get-Command php -ErrorAction SilentlyContinue).Source }

# Sin el php.ini del sitio, el PHP de Local no carga curl y el smoke HTTP falla con "HTTP 0".
if (-not $env:PHPRC) {
  $sitesFile = "$env:APPDATA\Local\sites.json"
  if (Test-Path $sitesFile) {
    $site = (Get-Content $sitesFile -Raw | ConvertFrom-Json).PSObject.Properties.Value | Where-Object { $_.domain -eq "devtatipilates.local" } | Select-Object -First 1
    $conf = if ($site) { "$env:APPDATA\Local\run\$($site.id)\conf\php" }
    if ($conf -and (Test-Path "$conf\php.ini")) { $env:PHPRC = $conf }
  }
}

$names = "Sintaxis PHP", "BOM UTF-8", "Debug code", "Release desactualizado", "Version del plugin", "Compatibilidad WordPress", "Lint JS/CSS/YAML", "Smoke portal/PWA"
$results = @{}

function Mark($i, $state, $note) { $script:results[$i] = @{ State = $state; Note = $note } }
function Header($t) { Write-Host $t -ForegroundColor Cyan }
function Summary {
  Write-Host "`nResumen pre-release" -ForegroundColor Cyan
  for ($i = 1; $i -le $names.Count; $i++) {
    $r = $results[$i]
    if (-not $r) { $r = @{ State = "pending"; Note = "No ejecutado" } }
    $sym = switch ($r.State) { "pass" { "OK  " } "fail" { "FAIL" } default { "WARN" } }
    $color = switch ($r.State) { "pass" { "Green" } "fail" { "Red" } default { "Yellow" } }
    Write-Host ("{0,-28} | " -f $names[$i - 1]) -NoNewline
    Write-Host $sym -ForegroundColor $color -NoNewline
    Write-Host " | $($r.Note)"
  }
  Write-Host ""
}
function Fail($i, $note) { Mark $i "fail" $note; Summary; exit 1 }
function Ask($q) { if ($Yes) { Write-Host "-Yes activo: $q -> s"; return $true }; ((Read-Host "$q (s/n)").ToLower() -in "s", "si", "sí") }

$excluded = '\\(release|dev|\.git|vendor|node_modules)\\'
$phpFiles = Get-ChildItem -Recurse -File -Filter *.php | Where-Object { $_.FullName -notmatch $excluded -and $_.Name -notlike '._*' }

Write-Host "Pre-release check Tati Pilates`nRaiz: $Root`n" -ForegroundColor Cyan

Header "CHECK 1 - Sintaxis PHP"
if (-not $php -or -not (Test-Path $php)) { Fail 1 "PHP no encontrado (define TP_PHP)" }
$bad = @()
foreach ($f in $phpFiles) {
  $out = & $php -l $f.FullName 2>&1 | Where-Object { $_ -notmatch 'Imagick|^$' }
  if ($LASTEXITCODE -ne 0) { $bad += $f.FullName; $out }
}
if ($bad.Count) { Fail 1 "$($bad.Count) archivo(s) con error" }
Mark 1 "pass" "Todos los .php pasan php -l"

Header "CHECK 2 - BOM UTF-8"
$bom = $phpFiles | Where-Object { $b = [IO.File]::ReadAllBytes($_.FullName); $b.Length -ge 3 -and $b[0] -eq 0xEF -and $b[1] -eq 0xBB -and $b[2] -eq 0xBF }
if ($bom) { $bom | ForEach-Object { "BOM: $($_.FullName)" }; Fail 2 "BOM detectado" }
Mark 2 "pass" "Sin BOM en archivos PHP"

Header "CHECK 3 - Debug code"
$dbg = $phpFiles | Select-String -Pattern 'var_dump\(|print_r\(|var_export\(|echo\s+[''"]<pre|die\([''"]debug[''"]\)|exit\([''"]test[''"]\)'
if ($dbg) { $dbg | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line.Trim())" }; Fail 3 "Debug code encontrado" }
Mark 3 "pass" "Sin patrones de debug"

Header "CHECK 4 - Carpeta release desactualizada"
if (Test-Path "release\tatipilates" -PathType Container) {
  Write-Host "Existe release\tatipilates\ como carpeta. Eliminala o regenerala antes del release." -ForegroundColor Yellow
  Mark 4 "warn" "Existe release/tatipilates/"; Summary; exit 1
}
$cl = & $php scripts\generate-changelog.php --check 2>&1 | Where-Object { $_ -notmatch 'Imagick|^$' }
if ($LASTEXITCODE -ne 0) { $cl; Fail 4 "CHANGELOG.md desactualizado" }
$cl
Mark 4 "pass" "Release temporal ausente y changelog vigente"

Header "CHECK 5 - Version del plugin"
$m = Select-String -Path tatipilates.php -Pattern '^\s*\*\s*Version:\s*(\S+)' | Select-Object -First 1
if (-not $m) { Fail 5 "Version no encontrada" }
$version = $m.Matches[0].Groups[1].Value
Write-Host "Version detectada: $version"
if (-not (Ask "La version $version es correcta?")) { Fail 5 "Version no confirmada" }
Mark 5 "pass" "Version $version confirmada"

Header "CHECK 6 - Compatibilidad WordPress local"
$wpRoot = if ($env:TP_WP_ROOT) { $env:TP_WP_ROOT } else { "D:\wps\devtatipilates\app\public" }
if ($TestedWp) { $wpVer = $TestedWp }
elseif (Test-Path "$wpRoot\wp-includes\version.php") {
  $wpVer = (& $php -r 'require $argv[1]; echo $wp_version;' "$wpRoot\wp-includes\version.php" 2>$null | Where-Object { $_ -notmatch 'Imagick|^$' } | Select-Object -Last 1)
} else { Write-Host "No se pudo detectar WordPress local. Define TP_WP_ROOT o usa -TestedWp X.Y."; Fail 6 "WordPress local no encontrado" }
if ($wpVer -notmatch '^(\d+\.\d+)') { Fail 6 "Version WordPress invalida" }
$wpMajor = $Matches[1]
$metaFile = Join-Path $Root "release-metadata.json"
if (-not (Test-Path $metaFile)) { Fail 6 "Metadata de release ausente" }
Write-Host "WordPress local detectado: $wpVer"
if (-not (Ask "La regresion fue probada en WordPress ${wpMajor}?")) { Fail 6 "Regresion WordPress no confirmada" }
$meta = Get-Content $metaFile -Raw | ConvertFrom-Json
if ($meta.tested -ne $wpMajor) {
  $meta.tested = $wpMajor
  [IO.File]::WriteAllText($metaFile, (($meta | ConvertTo-Json) + "`n"), (New-Object Text.UTF8Encoding($false)))
  Mark 6 "pass" "WordPress $wpMajor detectado y metadata actualizada"
} else { Mark 6 "pass" "WordPress $wpMajor detectado; metadata vigente" }

Header "CHECK 7 - Lint JS/CSS/YAML"
$lint = & $php tests\static-assets-lint.php 2>&1 | Where-Object { $_ -notmatch 'Imagick|^$' }
if ($LASTEXITCODE -ne 0) { $lint; Fail 7 "Lint estatico fallo" }
$lint
Mark 7 "pass" "Assets y workflows validos"

Header "CHECK 8 - Smoke portal/PWA"
if ($env:TP_PORTAL_URL) {
  $smoke = & $php tests\portal-pwa-smoke.php 2>&1 | Where-Object { $_ -notmatch 'Imagick|^$' }
  if ($LASTEXITCODE -ne 0) { $smoke; Fail 8 "Smoke portal/PWA fallo" }
  $smoke
  Mark 8 "pass" "Portal/PWA responde"
} else {
  Write-Host "TP_PORTAL_URL no definido; se omite smoke HTTP del portal." -ForegroundColor Yellow
  Mark 8 "pass" "Omitido sin TP_PORTAL_URL"
}

Summary

if ($NoZip) { Write-Host "-NoZip: ZIP no generado."; exit 0 }
if (-not (Ask "Generar ZIP?")) { Write-Host "ZIP no generado."; exit 0 }

New-Item -ItemType Directory -Force release | Out-Null
$zipPath = Join-Path $Root ("release\tatipilates-$version-" + (Get-Date -Format "yyyyMMdd-HHmm") + ".zip")
$tmp = Join-Path ([IO.Path]::GetTempPath()) ("tatipilates-release-" + [guid]::NewGuid().ToString("N"))
$dest = Join-Path $tmp "tatipilates"
New-Item -ItemType Directory -Force $dest | Out-Null
foreach ($p in "tatipilates.php", "uninstall.php", "includes", "admin", "public", "assets") { Copy-Item -Recurse $p $dest }
Get-ChildItem $dest -Recurse -Force | Where-Object { $_.Name -eq ".DS_Store" -or $_.Name -like ".*" } | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue

# ZIP con separadores "/" (Compress-Archive de PS 5.1 usa "\", que rompe WordPress en Linux).
Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$zip = [IO.Compression.ZipFile]::Open($zipPath, "Create")
try {
  foreach ($f in Get-ChildItem $dest -Recurse -File) {
    $entry = "tatipilates/" + $f.FullName.Substring($dest.Length + 1).Replace("\", "/")
    [void][IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $entry, "Optimal")
  }
} finally { $zip.Dispose() }
Remove-Item -Recurse -Force $tmp
Write-Host "ZIP generado: $zipPath" -ForegroundColor Green
