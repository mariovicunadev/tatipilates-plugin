# Tati Pilates (plugin privado de WordPress)

Las reglas completas del proyecto están en `docs/context/AGENTS.md`: leerlo al empezar cualquier tarea, junto con los archivos de contexto que indica.

## Entorno (Windows)

- Repo y fuente de verdad: `D:\Proyectos\Mi Pilates Admin`.
- Sitio local: `https://devtatipilates.local/` (LocalWP, sitio `devtatipilates`, id `PikzcSaOn`). El sitio tiene que estar **iniciado** en la app Local.
- `wp-content\plugins\tatipilates` es un directory junction que apunta a este repo: cualquier edición aquí se ve al instante en el sitio local. No reemplazarlo por una copia.
- Detalles de PHP, tests y rutas: `docs/DEV-WINDOWS.md`.

## Flujo de trabajo: local -> live (no hay staging)

1. Hacer el cambio en el repo y probarlo en `devtatipilates.local` (desktop y mobile si toca UI).
2. Registrar el cambio en `docs/releases/changelog.json` (en `unreleased`) y regenerar con `php scripts/generate-changelog.php`; nunca editar `CHANGELOG.md` a mano. Antes de publicar, mover esas entradas a una release nueva con la versión y fecha a publicar y dejar `unreleased` vacío: `release.ps1` lo exige.
3. `.\pre-release-check.ps1 -Yes` (con `$env:TP_PORTAL_URL='http://devtatipilates.local/mi-pilates'`).
4. Commit (Conventional Commits en inglés) y `git push origin main`, **solo cuando el usuario lo pida**.
5. `.\release.ps1 -Channel stable -Version X.Y.Z` publica en live; el sitio live lo ve como actualización en wp-admin.
6. `git pull --ff-only` después del workflow, porque el bot commitea el bump de versión.

Runbook completo: `docs/release-runbook.md`.

## Nunca

- Commitear `CONTEXTO.md`, `dev/`, `release/`, ZIPs, tokens ni credenciales.
- Editar el plugin directamente en live sin replicar el cambio en Git.
