# Data model

Tablas propias del plugin. Todas usan el prefijo real de WordPress:

```php
$wpdb->prefix . 'tp_nombre'
```

Nunca asumir `wp_`.

## `tp_horarios`

Horarios configurables de clases.

Campos principales:

- `id`
- `dia_semana`
- `hora_inicio`
- `modalidad`
- `cupo_maximo`
- `activo`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `dia_hora (dia_semana, hora_inicio)`
- `activo (activo)`
- `activo_dia_hora (activo, dia_semana, hora_inicio)`

Relaciones:

- `tp_reservas.horario_id`

Reglas:

- Si un horario se desactiva, se cancelan sus reservas futuras.

## `tp_alumnas`

Perfil interno vinculado a `wp_users`.

Campos principales:

- `id`
- `wp_user_id`
- `plan`
- `activa`
- `notas`
- `historia_medica`
- `alergias`
- `motivo_pilates`
- `fecha_nacimiento`
- `fecha_inicio_pilates`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `UNIQUE KEY wp_user_id (wp_user_id)`
- `plan (plan)`
- `activa (activa)`
- `fecha_nacimiento`
- `fecha_inicio_pilates`

Relaciones:

- `wp_user_id` apunta a `wp_users.ID`.
- Referenciada por reservas, recuperaciones, pagos, milestones y notificaciones.

Reglas:

- Desactivar alumna cancela reservas futuras y devuelve recuperaciones si aplica.
- Eliminar alumna requiere no tener historial relevante.
- La eliminacion incluye validacion de `tp_milestones`.

## `tp_reservas`

Reservas de clases normales y recuperaciones.

Campos principales:

- `id`
- `alumna_id`
- `horario_id`
- `fecha`
- `tipo`
- `recuperacion_id`
- `estado`
- `creada_por`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `UNIQUE KEY unique_reserva (alumna_id, horario_id, fecha)`
- `horario_fecha (horario_id, fecha)`
- `alumna_fecha (alumna_id, fecha)`
- `fecha_estado (fecha, estado)`
- `estado_fecha (estado, fecha)`
- `alumna_estado_fecha (alumna_id, estado, fecha)`

Estados esperados:

- `reservada`
- `asistio`
- `falto`
- `cancelada`

Tipos:

- `normal`
- `recuperacion`

Reglas:

- Una alumna no puede reservar dos veces el mismo horario/fecha.
- `cupo_plan_semana()` es la fuente central de limites semanales.
- Cancelar reserva con recuperacion debe devolver la recuperacion.

## `tp_recuperaciones`

Creditos de recuperacion.

Campos principales:

- `id`
- `alumna_id`
- `reserva_origen_id`
- `reserva_uso_id`
- `fecha_origen`
- `fecha_limite`
- `estado`
- `motivo`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `alumna_estado_limite (alumna_id, estado, fecha_limite)`
- `estado_limite (estado, fecha_limite)`
- `reserva_origen_id`
- `reserva_uso_id`

Estados:

- `pendiente`
- `usada`
- `vencida`
- `cancelada`

Reglas:

- Vencimiento estandar: 3 meses desde fecha origen.
- Se expiran por cron diario.
- Usarlas debe ser atomico con la reserva creada.

## `tp_pagos`

Registro mensual simple de pago.

Campos principales:

- `id`
- `alumna_id`
- `mes`
- `fecha_pago`
- `notas`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `UNIQUE KEY unique_pago_mes (alumna_id, mes)`
- `alumna_mes (alumna_id, mes)`
- `mes (mes)`

Reglas:

- No maneja montos ni moneda.
- El estado de pago decide si la alumna puede reservar.
- Hay periodo de gracia mensual documentado en logica de pagos.

## `tp_milestones`

Logros de alumnas.

Campos principales:

- `id`
- `alumna_id`
- `titulo`
- `fecha`
- `estado`
- `created_at`

Indices:

- `PRIMARY KEY (id)`
- `alumna_fecha (alumna_id, fecha)`
- `alumna_estado_fecha (alumna_id, estado, fecha)`

Reglas:

- El panel de alumna solo muestra la seccion si hay logros.
- Eliminar alumna considera esta tabla como historial.

## `tp_notificaciones`

Notificaciones internas para admin y alumnas.

Campos principales:

- `id`
- `alumna_id`
- `tipo`
- `referencia`
- `titulo`
- `mensaje`
- `url_accion`
- `canal`
- `visible_admin`
- `visible_alumna`
- `visto_admin`
- `visto_alumna`
- `eliminado_admin`
- `eliminado_alumna`
- `created_at`
- `visto_admin_at`
- `visto_alumna_at`

Indices:

- `PRIMARY KEY (id)`
- `alumna_created (alumna_id, created_at)`
- `tipo_referencia (tipo, referencia)`
- `admin_estado_fecha`
- `alumna_estado_fecha`

Reglas:

- Usa soft-delete por contexto.
- Campanita de alumna muestra solo pendientes.
- X de campanita marca como vista y quita del dropdown.
- Panel de notificaciones conserva la notificacion vista.

## Opciones WordPress

- `tp_db_version`
- `tp_schema_version`
- `tp_portal_page_id`
- `tp_rewrite_version`
- `tp_notificaciones_config`
- `tp_updater_config`
- `tp_delete_data_on_uninstall`

`tp_updater_config` conserva solo el estado habilitado y el canal. Durante una
version de transicion puede contener una clave `token` legacy; se elimina
automaticamente cuando el plugin detecta `TP_GITHUB_TOKEN` en `wp-config.php` o
en el entorno.

## Uninstall

`uninstall.php` conserva datos por defecto. Si `tp_delete_data_on_uninstall` esta apagada, solo limpia crons y sale sin borrar tablas, opciones ni roles.

Si la Zona peligrosa activa `tp_delete_data_on_uninstall = 1`, `uninstall.php` elimina:

- tablas `tp_*`;
- opciones del plugin;
- rol `tp_admin_pilates`;
- capabilities `tp_manage_pilates` y `tp_view_medical_data` del administrador.

## Backups JSON

El backup propio del plugin incluye:

- filas de todas las tablas `tp_*`;
- usuarios WordPress vinculados a alumnas, sin contrasenas;
- configuracion de notificaciones.

La importacion sincroniza por ID y por claves logicas conocidas: si una fila existe se actualiza, y si no existe se inserta. Esto evita duplicados y permite restaurar datos faltantes aunque algunos IDs hayan cambiado entre instalaciones.
