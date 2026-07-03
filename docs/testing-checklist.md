# Testing checklist

Checklist para probar una version antes de publicarla a `stable`.

## Preparacion

- Confirmar que el sitio correcto esta en el canal esperado:
  - staging: `Tati Pilates > Configuracion > Actualizaciones privadas > staging`
  - live: `Tati Pilates > Configuracion > Actualizaciones privadas > stable`
- Confirmar version instalada en `Plugins > Tati Pilates`.
- Limpiar cache del navegador si se probaron cambios de CSS/JS/PWA.
- Usar al menos:
  - un usuario administrador WordPress;
  - un usuario con rol Admin Pilates;
  - una alumna activa;
  - una alumna inactiva o sin pago, si aplica.

## Portal alumna

- Login en `/mi-pilates` con alumna activa.
- Login falla con mensaje generico para credenciales incorrectas.
- Reset de contrasena mantiene al usuario dentro de `/mi-pilates`.
- Logout vuelve al portal.
- Alumna inactiva ve mensaje de perfil no activo.
- Usuario sin rol valido no entra al portal sensible.

## Reservas

- Alumna puede reservar una clase disponible.
- No puede superar el cupo del horario.
- No puede superar su limite semanal de plan.
- No puede reservar si pago esta vencido fuera del periodo de gracia.
- Plan individual muestra bloqueo/coordinar con Tatiana.
- Semana actual oculta dias ya pasados en la vista de reservas.
- Semanas futuras muestran todos los dias disponibles.
- Semanas pasadas conservan historial completo.
- Cards de horarios en mobile se ven en dos columnas y sin textos cortados.

## Cancelaciones y ausencias

- Alumna puede cancelar una reserva propia futura.
- Alumna no puede cancelar reserva de otra alumna.
- Reportar ausencia aparece solo si hay clases futuras reportables.
- Reportar ausencia genera recuperacion cuando corresponde.
- Se crea notificacion de ausencia reportada.

## Recuperaciones

- Recuperaciones pendientes aparecen como disponibles.
- Usar recuperacion crea reserva de tipo recuperacion.
- Si falla uso de recuperacion, no queda reserva inconsistente.
- Recuperaciones vencidas expiran con cron o accion correspondiente.

## Agenda

- Vista `Agenda` es accesible para alumnas.
- Agenda muestra cupos, alumnas y estados esperados.
- Admin o Admin Pilates puede ver agenda privada.
- Alumna no ve datos administrativos que no correspondan.

## Panel lateral

- "Mis logros" solo aparece si la alumna tiene logros.
- "Reportar ausencia" solo aparece si hay reservas reportables.
- Progreso semanal muestra clases usadas/restantes correctamente.
- Recuperaciones muestra contador correcto.

## Notificaciones

- Campanita muestra solo notificaciones pendientes.
- Badge baja al marcar una notificacion como vista.
- X de notificacion la quita de la campanita y la marca como vista.
- Panel de notificaciones mantiene la notificacion como vista.
- Eliminar notificacion hace soft-delete para la alumna.
- Mobile: dropdown no se sale del viewport.

## Admin

- Admin puede crear/editar/desactivar alumna.
- Al desactivar alumna se cancelan reservas futuras y se devuelven recuperaciones si aplica.
- Eliminar alumna con historial queda bloqueado.
- Admin puede crear horarios.
- Al desactivar horario se cancelan reservas futuras.
- Admin puede registrar pagos.
- Admin puede marcar asistencia/falta.
- Admin puede crear logros y recuperaciones manuales.

## Admin Pilates

- Puede acceder a pantallas permitidas por `tp_manage_pilates`.
- Sin `tp_view_medical_data`, no ve campos medicos en ficha o formulario.
- Editar nombre, plan, fechas o notas sin acceso medico conserva los valores
  medicos existentes.
- Un `POST` manual de campos medicos sin la capability recibe 403.
- Al asignar `tp_view_medical_data` a un usuario concreto, aparecen y se pueden
  editar los tres campos.
- No debe ver opciones nativas innecesarias de WordPress.
- No debe poder hacer acciones fuera de la capability configurada.

## Emails

- Crear alumna intenta enviar credenciales.
- Si falla `wp_mail`, la pantalla muestra credenciales temporales.
- Reset de contrasena envia link al portal.
- Mensajes de reset no revelan si el correo existe.

## PWA

- Manifest carga.
- Iconos cargan.
- Offline page responde.
- Cache no deja CSS/JS viejos despues de update.
- Instalacion en iOS/Android no rompe login.

## Updater privado

- Staging detecta versiones `rc` del canal `staging`.
- Live detecta solo versiones finales del canal `stable`.
- Live no detecta versiones `rc`.
- Token incorrecto no expone errores sensibles al usuario.
- Guardar updater limpia cache de manifest.
- `Escritorio > Actualizaciones > Comprobar de nuevo` refresca updates.

## Backups

- Un backup nuevo declara `format_version: 2`.
- Un backup legacy v1 sigue validando e importando.
- Archivos mayores al límite, JSON truncado, tablas/campos desconocidos, tipos,
  longitudes, enums y referencias inválidas se rechazan antes de importar.
- Un `format_version` futuro se rechaza con mensaje claro y sin cambios parciales.
- Un fallo de base durante la importación revierte filas y restaura
  `FOREIGN_KEY_CHECKS`.

Checks automatizados:

```zsh
TP_WP_LOAD=/ruta/a/wp-load.php php tests/backup-import-validation.php
php tests/updater-token-configuration.php
php tests/medical-data-access.php
```

El check del updater usa stubs y tokens ficticios: valida fallback legacy,
ausencia de token, precedencia constante/entorno, limpieza de `wp_options` y
que el formulario no pueda modificar el secreto.

El check de datos medicos valida la separacion de roles, SQL de listados,
lectura autorizada/rechazada y preservacion de columnas en ediciones operativas.

## Pre-release local

Antes de publicar:

```zsh
./pre-release-check.sh --yes
```

Debe pasar:

- PHP syntax.
- BOM UTF-8.
- Debug code.
- Sin `release/tatipilates/`.
- Version correcta.
