# AGENTS.md

## Convenciones de código
- Seguir WordPress Coding Standards y los patrones existentes del repositorio.
- Usar clases `TP_*`; dominio y servicios en `includes/`, controladores en `admin/` o `public/`, render en `views/`.
- Mantener lógica de negocio y consultas fuera de las vistas.
- Usar `$wpdb->prefix`, queries preparadas, formatos tipados y checks estrictos `false ===`.
- Validar nonce, capability y ownership antes de mutar datos.
- Sanitizar entradas y escapar cada salida según su contexto.
- Usar transacciones cuando una operación afecte varias tablas.
- Mantener `assets/css/tatipilates-portal.min.css` sincronizado con `assets/css/portal.css`.
- Probar desktop y mobile cuando se modifique UI.

## Estilo de commits
Conventional Commits en inglés:

- `feat: ...`
- `fix: ...`
- `docs: ...`
- `chore: ...`

No hacer commit ni push hasta que el usuario lo pida explícitamente.

## Comandos
- Build: no hay compilación; el paquete se genera con `./pre-release-check.sh --yes`.
- Test: `./pre-release-check.sh --yes`; para backups usar `TP_WP_LOAD=/ruta/a/wp-load.php php tests/backup-import-validation.php`; para el updater usar `php tests/updater-token-configuration.php`; para permisos medicos usar `php tests/medical-data-access.php` y `php tests/medical-data-encryption.php`; complementar con `docs/testing-checklist.md`.
- Run local: iniciar LocalWP y abrir `https://devtatipilates.local/`; el plugin está enlazado desde `/Users/vicunav/Documents/wps/app/public/wp-content/plugins/tatipilates`.

## Reglas para el agente
- Siempre actualizar PROGRESS.md al final de cada sesión.
- No tomar decisiones de arquitectura sin registrar en DECISIONS.md.
- Consultar SPEC.md antes de agregar features nuevas.
- Al iniciar una tarea, leer `PROJECT.md`, `SPEC.md`, `ARCHITECTURE.md`, `DECISIONS.md`, `TASKS.md`, `PROGRESS.md` y `../../CHANGELOG.md`.
- Tratar `CHANGELOG.md` como historial detallado y `PROGRESS.md` como resumen operativo.
- Registrar cambios relevantes en `CHANGELOG.md` sin reescribir versiones publicadas.
- Preservar cambios locales existentes que no pertenezcan a la tarea.
- No commitear `CONTEXTO.md`, `dev/`, `release/`, ZIPs, tokens ni credenciales.
- No pegar ni solicitar tokens en chats; indicar cómo crear o rotar credenciales.
- Antes de staging, correr pre-release, revisar diff y publicar una versión `-rc.N`.
- No promover a stable sin prueba explícita en staging.
- Después de workflows de release, ejecutar `git pull --ff-only` y verificar versión, manifest, tag y asset.
- Para detalles ampliados consultar `docs/architecture.md`, `docs/data-model.md`, `docs/product-decisions.md`, `docs/security-checklist.md`, `docs/testing-checklist.md` y `docs/release-runbook.md`.
