# Entorno de desarrollo en Windows

Actualizado: 2026-10-10. Reemplaza las rutas de Mac que aparecen en `CONTEXTO.md` y `pre-release-check.sh`.

## Rutas

| Qué | Ruta |
|---|---|
| Repo del plugin (fuente de verdad) | `D:\Proyectos\Mi Pilates Admin` |
| Sitio LocalWP | `D:\wps\devtatipilates` (`https://devtatipilates.local`) |
| WordPress del sitio | `D:\wps\devtatipilates\app\public` |
| Plugin dentro del sitio | `...\wp-content\plugins\tatipilates` (copia descargada de SiteGround) |
| Remoto | `https://github.com/mariovicunadev/tatipilates-plugin` |

El sitio local viene de una exportación de SiteGround (v1.3.2, igual al repo hash por hash al 2026-10-10).
La carpeta antigua `D:\Proyectos\wps` es el sitio de la Mac, ya no se usa.

## Herramientas instaladas

Node 24, pnpm 11.9, git, GitHub CLI (`gh`), PHP 8.3 + Composer (para repos con `composer.json`),
Ruby 3.3 (lint YAML), ExifTool e ImageMagick, LocalWP.

Los tests usan el **PHP de Local del sitio (8.3.36)** con su `php.ini`, no el PHP del sistema.
`pre-release-check.ps1` define `PHPRC` solo; sin ese `php.ini` no carga `curl` y el smoke del portal falla con "HTTP 0".

## Tokens y claves

- El sitio local **no necesita** `TP_GITHUB_TOKEN` ni `TP_DATA_ENCRYPTION_KEY`. Sin token el actualizador queda
  desactivado ("TP_GITHUB_TOKEN no esta configurado"), y eso es lo esperado en local.
- `TP_GITHUB_TOKEN` solo existe en el `wp-config.php` de staging y live.
- Las credenciales de GitHub están en el Administrador de credenciales de Windows (`gh auth login`, cuenta `mariovicunadev`).
  No hay secretos en el repo.

## Correr los tests

```powershell
$env:PHPRC="$env:APPDATA\Local\run\PikzcSaOn\conf\php"
$env:TP_WP_LOAD='D:\wps\devtatipilates\app\public\wp-load.php'
$env:TP_PORTAL_URL='http://devtatipilates.local/mi-pilates'   # http: el cert de Local es autofirmado
$php="$env:APPDATA\Local\lightning-services\php-8.3.36+0\bin\win64\php.exe"
Get-ChildItem tests\*.php | % { "$($_.Name): " + (& $php $_.FullName 2>&1 | Select -Last 1) }
```

El sitio debe estar **iniciado** en Local. El id `PikzcSaOn` es el del sitio en Local; si se recrea el sitio, cambia
(ver `%APPDATA%\Local\sites.json`). Los tests de datos médicos escriben en la base de datos local, nunca en producción.

## Flujo de release

Desde 2026-10-23 no hay staging: el plugin dentro de `devtatipilates.local` es
un directory junction al repo (`D:\wps\devtatipilates\app\public\wp-content\plugins\tatipilates`
-> `D:\Proyectos\Mi Pilates Admin`), así que probar en LocalWP reemplaza la
prueba en staging.

1. `.\pre-release-check.ps1` (8 chequeos; `-NoZip` para no generar el ZIP).
2. Probar el cambio en `https://devtatipilates.local/`.
3. `git add -A; git commit -m "..."; git push origin main`.
4. `.\release.ps1 -Channel stable -Version X.Y.Z` promueve directo a live.

`release.ps1` exige árbol limpio y `main` sincronizado con `origin`, pide confirmación y espera el workflow.
Los workflows son manuales (`workflow_dispatch`): hacer push no publica nada por sí solo.
Live lee `updates.json` (canal `stable`) con su propio token. El canal `staging` y
`release.ps1 -Channel staging` siguen disponibles pero ya no son un paso obligatorio.

## Particularidades de Windows

- `autocrlf=true`; `.gitattributes` fija LF en `*.sh`. En este repo algunos `.php` aparecen como modificados con 0 líneas
  de diferencia (solo fin de línea); es ruido, no cambios reales.
- `tests/static-assets-lint.php` ya es portable (`where` en Windows, `command -v` en POSIX) y requiere Node y Ruby en el PATH.
- `pre-release-check.sh` se conserva por referencia, pero apunta a rutas de la Mac. Usar el `.ps1`.
- Antes de usar un script `.sh` del repo, ejecutarlo con Git Bash, no con PowerShell.
- Los archivos `._*` y `.DS_Store` son basura de macOS: borrarlos si reaparecen al copiar desde un disco de Mac.
