# Publica una version del plugin disparando los workflows de GitHub Actions.
#   .\release.ps1 -Channel staging -Version 1.3.3-rc.1 [-Notes "texto"]
#   .\release.ps1 -Channel stable  -Version 1.3.3      [-Notes "texto"]
# Staging y live toman la version nueva desde updates.json (canal staging / stable).
param(
  [Parameter(Mandatory)][ValidateSet("staging", "stable")][string]$Channel,
  [Parameter(Mandatory)][string]$Version,
  [string]$Notes = "",
  [switch]$Yes
)
$ErrorActionPreference = "Stop"
Set-Location $PSScriptRoot

if ($Channel -eq "staging" -and $Version -notmatch '^\d+\.\d+\.\d+-rc\.\d+$') { throw "Staging requiere formato 1.0.2-rc.1" }
if ($Channel -eq "stable" -and $Version -notmatch '^\d+\.\d+\.\d+$') { throw "Stable requiere formato 1.0.2" }
if (-not (Get-Command gh -ErrorAction SilentlyContinue)) { throw "Falta GitHub CLI (winget install GitHub.cli)" }
gh auth status *> $null
if ($LASTEXITCODE -ne 0) { throw "No hay sesion de GitHub. Ejecuta: gh auth login --scopes repo,workflow" }

# El workflow corre sobre origin/main: el codigo debe estar commiteado y pusheado.
$dirty = git status --porcelain | Where-Object { $_ -notmatch '^\?\?' }
if ($dirty) { throw "Hay cambios sin commitear:`n$($dirty -join "`n")" }
git fetch origin main --quiet
$ahead = git rev-list --count origin/main..HEAD
$behind = git rev-list --count HEAD..origin/main
if ([int]$ahead -gt 0) { throw "Tienes $ahead commit(s) sin pushear. Ejecuta: git push origin main" }
if ([int]$behind -gt 0) { throw "origin/main tiene $behind commit(s) que no tienes. Ejecuta: git pull" }

$workflow = if ($Channel -eq "staging") { "publish-staging-release.yml" } else { "promote-stable-release.yml" }
if (-not $Notes) { $Notes = if ($Channel -eq "staging") { "Release candidate para pruebas en staging." } else { "Version aprobada para live." } }

Write-Host "Se publicara v$Version en el canal $Channel ($workflow) desde origin/main $(git rev-parse --short HEAD)." -ForegroundColor Cyan
if (-not $Yes -and ((Read-Host "Continuar? (s/n)").ToLower() -notin "s", "si", "sí")) { Write-Host "Cancelado."; exit 0 }

gh workflow run $workflow -f "version=$Version" -f "notes=$Notes"
Start-Sleep 6
$run = gh run list --workflow $workflow --limit 1 --json databaseId --jq '.[0].databaseId'
Write-Host "Workflow en ejecucion (run $run). Esperando resultado..."
gh run watch $run --exit-status
if ($LASTEXITCODE -eq 0) {
  git pull --ff-only
  Write-Host "Listo: v$Version publicada en $Channel. Los sitios la veran al consultar updates.json." -ForegroundColor Green
} else { Write-Host "El workflow fallo. Revisa: gh run view $run --log-failed" -ForegroundColor Red; exit 1 }
