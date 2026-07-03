# Changelog

Todos los cambios relevantes del plugin se documentan en este archivo.

El formato sigue la idea de Keep a Changelog y las versiones usan semver:

- `MAJOR`: cambios incompatibles o migraciones grandes.
- `MINOR`: funcionalidades nuevas.
- `PATCH`: fixes y mejoras pequenas.

## [Unreleased]

### Fixed

- Invalida el manifiesto privado cuando WordPress fuerza una nueva comprobacion de plugins, evitando esperar 30 minutos para ver un release reciente.
- Mueve los backups automaticos fuera del webroot, migra y verifica por hash los JSON legacy de `uploads`, y permite descargar el ultimo archivo mediante un handler administrativo autenticado.
- Notifica al administrador dentro del plugin y por email cuando el backup automatico no puede escribir en el directorio privado, con deduplicacion diaria.
- Restringe el generador de datos demo a entornos `local` o `development` con opt-in explicito, reemplaza las credenciales fijas por passwords aleatorios de un solo uso y bloquea el handler en otros entornos.
- En produccion rota las credenciales de cuentas `*.demo@tatipilates.test`, retira privilegios al admin demo y conserva intactos sus datos para revision manual.
- Versiona los backups con `format_version: 2` y valida completamente usuarios, tablas, columnas, tipos, longitudes, enums, relaciones y limites antes de abrir la transaccion de importacion.
- Endurece el upload de backups con limite configurable de 10 MiB, verificacion de error, origen HTTP, extension, MIME y tamano real; conserva compatibilidad explicita con backups legacy v1.
- Rechaza formatos futuros con un mensaje claro y garantiza rollback/restauracion de foreign keys ante cualquier fallo de escritura.
- Mueve el PAT del updater privado a `TP_GITHUB_TOKEN` en `wp-config.php` o el entorno, oculta el secreto de la configuracion publica y elimina automaticamente su copia legacy de `wp_options` al detectar la fuente segura.
- Mantiene una transicion compatible para instalaciones con PAT legacy, muestra advertencias operativas y evita que el formulario administrativo pueda guardar, reemplazar o borrar el token.
- Separa el acceso a historia medica, alergias y motivo de Pilates mediante `tp_view_medical_data`, asignada por defecto solo a administradores de WordPress y no al rol Admin Pilates.
- Elimina los campos medicos de listados y perfiles operativos con columnas SQL explicitas; las lecturas completas y escrituras requieren la capability dedicada, y las ediciones sin permiso preservan los valores existentes.
- Detecta la version de WordPress del entorno local durante pre-release y la usa como metadata probada en los manifiestos de staging y stable.
- Agrega el rol `Tatiana` con la misma gestion operativa de Admin Pilates y acceso explicito a datos medicos; Admin Pilates conserva la gestion sin esa capability.
- Conserva el rol `Tatiana` al validar, exportar e importar backups y lo elimina durante una desinstalacion destructiva.

## [1.2.2] - 2026-06-18

### Fixed

- Corrige la alineacion de checkbox, titulo y descripcion en las opciones de recordatorios del panel de Configuracion.

## [1.2.1] - 2026-06-18

### Fixed

- Actualiza el updater privado al nuevo repositorio `mariovicunadev/tatipilates-plugin` para autorizar correctamente la descarga de assets privados.

## [1.2.0] - 2026-06-18

### Added

- Agrega backups JSON propios del plugin con descarga manual, generacion bajo demanda, importacion idempotente y cron diario.
- Agrega Zona peligrosa para controlar si `uninstall.php` conserva o elimina la data del plugin.

### Changed

- Rediseña la experiencia visual del admin y del portal PWA con una interfaz mas premium, moderna y tactil, manteniendo la paleta base.
- Refina la vista de login del portal con mejor balance visual, jerarquia y estados tactiles.
- Corrige la jerarquia de navegacion del dashboard admin para que todos los accesos compartan el mismo peso visual.
- Corrige el contraste hover de los botones destructivos del dashboard admin.
- Corrige el dropdown de notificaciones del portal en mobile para evitar overflow horizontal.
- Las notificaciones del dashboard admin se pueden marcar como vistas o eliminar sin recargar la pagina.
- Las notificaciones del portal se pueden marcar como vistas o eliminar sin recargar la pagina.
- Corrige el envio AJAX de las acciones de notificacion del portal cuando el formulario incluye un campo `action`.
- `uninstall.php` conserva datos por defecto y solo borra tablas/opciones/roles si la opcion explicita de borrado esta activada.
- El selector semanal del Dashboard usa semanas completas, navega automaticamente al cambiar y elimina los botones redundantes.
- La navegacion entre semanas actualiza solo el Dashboard para evitar recargar todo wp-admin y sus plugins en cada cambio.
- El selector semanal muestra el rango de lunes a domingo y abre el calendario al pulsar cualquier parte del control.
- La metrica semanal de recuperaciones cuenta los creditos generados por faltas de esa semana, en lugar de las reservas que usaron un credito.

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
