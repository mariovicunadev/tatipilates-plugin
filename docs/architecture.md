# Architecture

Mapa de responsabilidades del plugin.

## Entrada principal

Archivo:

```text
tatipilates.php
```

Responsabilidades:

- Define `TP_VERSION`.
- Define rutas y URL del plugin.
- Registra autoload de clases `TP_*`.
- Registra activation/deactivation hooks.
- Instancia modulos principales en `plugins_loaded`.
- Configura cron diario para recuperaciones y notificaciones.

## Capas

```text
tatipilates.php
-> includes/     logica de dominio y servicios
-> admin/        wp-admin, formularios, vistas admin
-> public/       portal alumna, PWA y vistas publicas
-> assets/       CSS, JS, iconos
-> docs/         documentacion operativa
```

## Clases principales

### `TP_Activator`

Archivo:

```text
includes/class-activator.php
```

Responsabilidades:

- Crear tablas.
- Crear indices.
- Crear roles/capabilities.
- Crear pagina `/mi-pilates`.
- Programar cron diario.
- Migrar schema con `ensure_schema()`.

### `TP_Roles`

Archivo:

```text
includes/class-roles.php
```

Responsabilidades:

- Roles `tp_alumna` y `tp_admin_pilates`.
- Capability `tp_manage_pilates`.
- Capability independiente `tp_view_medical_data`, asignada por defecto solo
  a `administrator`.
- Helpers de rol.
- Redirecciones para alumnas.
- Ocultar admin bar para alumnas.
- URL central del portal.

### `TP_Admin`

Archivo:

```text
admin/class-admin.php
```

Responsabilidades:

- Menus de wp-admin.
- Render de vistas admin.
- Handlers `admin_post_*`.
- Reservas admin.
- Reset de estudiante.
- Notificaciones admin.
- Configuracion de recordatorios, updater y datos demo.

### `TP_Portal`

Archivo:

```text
public/class-portal.php
```

Responsabilidades:

- Shortcode `[tatipilates_portal]`.
- Login, logout y reset de contrasena.
- Render del dashboard alumna.
- Reservas/cancelaciones/reporte de ausencia.
- Agenda publica para alumnas.
- Notificaciones de alumna.
- PWA manifest/service worker/offline.

### `TP_Reservas`

Archivo:

```text
includes/class-reservas.php
```

Responsabilidades:

- Intentar reserva.
- Validar cupos.
- Validar limites semanales.
- Reservar semana/mes.
- Reservar con recuperacion.
- `cupo_plan_semana()` como fuente central de cupo semanal.

### `TP_Alumnas`

Archivo:

```text
includes/class-alumnas.php
```

Responsabilidades:

- Crear usuario WordPress + perfil alumna.
- Enviar credenciales.
- Actualizar perfil.
- Desactivar alumna y cancelar reservas futuras.
- Eliminar alumna con validaciones de historial.
- Logros.
- Buscar alumna por `wp_user_id`.

### `TP_Pagos`

Archivo:

```text
includes/class-pagos.php
```

Responsabilidades:

- Registrar/eliminar pagos.
- Estado mensual para reservas.
- Periodo de gracia.
- Formateo de fechas usado en vistas.

### `TP_Asistencia`

Archivo:

```text
includes/class-asistencia.php
```

Responsabilidades:

- Agenda semanal admin/publica.
- Marcar asistencia/falta.
- Crear recuperacion por ausencia.

### `TP_Recuperaciones`

Archivo:

```text
includes/class-recuperaciones.php
```

Responsabilidades:

- Listar recuperaciones.
- Crear recuperacion manual.
- Expirar vencidas.
- Eliminar recuperaciones.

### `TP_Notificaciones`

Archivo:

```text
includes/class-notificaciones.php
```

Responsabilidades:

- Crear/listar notificaciones.
- Marcar vista.
- Soft-delete.
- Recordatorios de clases, recuperaciones y pagos.
- Configuracion editable de textos.

### `TP_Updater`

Archivo:

```text
includes/class-updater.php
```

Responsabilidades:

- Leer `updates.json` desde GitHub.
- Respetar canal `staging` o `stable`.
- Inyectar updates privados en WordPress.
- Autorizar descargas de assets privados con token guardado.

### `TP_Helpers`

Archivo:

```text
includes/class-helpers.php
```

Responsabilidades:

- Logging.
- Normalizacion de fechas.
- Rangos semanales.
- Movimiento de semanas.
- Limites por plan.
- Formateo de reservas para agenda/notificaciones.

## Vistas

Admin:

```text
admin/views/*.php
```

Portal:

```text
public/views/portal.php
```

Regla:

- Las vistas deben renderizar variables preparadas.
- Evitar queries y logica de negocio en vistas.
- Escapar toda salida.

## Assets

```text
assets/css/admin.css
assets/css/portal.css
assets/css/tatipilates-portal.min.css
assets/js/portal.js
assets/icons/
```

Reglas:

- Cambios de portal CSS se hacen en `portal.css`.
- Mantener `tatipilates-portal.min.css` sincronizado si se modifica CSS usado por PWA/cache.
- Probar mobile cuando se toca portal.

## Publicacion

```text
pre-release-check.sh
release-metadata.json
.github/workflows/publish-staging-release.yml
.github/workflows/promote-stable-release.yml
updates.json
```

Ver:

```text
docs/updater-workflow.md
docs/release-runbook.md
```
