# Changelog

Todos los cambios relevantes del plugin se documentan en este archivo.

El formato sigue la idea de Keep a Changelog y las versiones usan semver:

- `MAJOR`: cambios incompatibles o migraciones grandes.
- `MINOR`: funcionalidades nuevas.
- `PATCH`: fixes y mejoras pequenas.

## [1.1.2] - 2026-06-06

### Added

- Agrega paquete de documentacion operativa y tecnica en `docs/`.
- Agrega checklists de testing, seguridad, release, troubleshooting, arquitectura, datos y desarrollo local.

## [1.1.0] - 2026-06-05

### Added

- Agrega updater privado por canales `staging` y `stable` usando GitHub Releases y `updates.json`.
- Agrega configuracion del canal y token de GitHub desde wp-admin.
- Agrega workflows de GitHub para publicar release candidate a staging y promover versiones estables a live.
- Documenta el flujo de releases por canales y el bootstrap inicial del updater.

## [1.0.1] - 2026-06-05

### Fixed

- Corrige el dropdown de notificaciones del portal en mobile para que no se salga del viewport.
- Agrega una X para quitar notificaciones de la campanita marcandolas como vistas en el panel de notificaciones.
- Oculta en la vista de reservas los dias ya pasados de la semana actual, manteniendo visibles las semanas historicas completas.
- Oculta las secciones vacias de logros y reporte de ausencia en el panel del estudiante.
- Compacta los horarios de reserva en mobile usando cards en dos columnas.

## [1.0.0] - 2026-06-03

### Added

- Version inicial del plugin Tati Pilates.
- Portal de alumnas en `/mi-pilates`.
- Gestion de alumnas, horarios, reservas, pagos, asistencia, recuperaciones, logros y notificaciones.
- PWA ligera para el portal.
- Datos de prueba locales.
- Script de pre-release para generar ZIPs validados.
