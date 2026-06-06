# Product decisions

Decisiones funcionales importantes del plugin.

## Portal de alumnas

- El portal vive en `/mi-pilates`.
- Login y reset de contrasena deben mantenerse dentro del portal.
- No redirigir alumnas a `wp-login.php` salvo casos nativos inevitables.
- Alumna debe tener usuario WordPress y registro en `tp_alumnas`.
- Alumna inactiva no puede usar el portal.

## Reservas

- El limite semanal depende del plan.
- `TP_Reservas::cupo_plan_semana()` es la fuente central para cupo semanal.
- Plan individual no reserva online; debe coordinar con Tatiana.
- En semana actual, la vista de reservas oculta dias ya pasados.
- En semanas historicas, se conserva el historial completo.
- La agenda sigue siendo accesible para alumnas.

## Ausencias y recuperaciones

- Reportar ausencia crea recuperacion si corresponde.
- La seccion "Reportar ausencia" solo aparece si hay reservas futuras reportables.
- Recuperaciones vencen a los 3 meses.
- Usar recuperacion debe ser atomico con la reserva.

## Alumnas

- Desactivar alumna cancela reservas futuras.
- Desactivar alumna devuelve recuperaciones asociadas cuando aplica.
- Eliminar alumna requiere no tener historial.
- `tp_milestones` cuenta como historial.

## Horarios

- Desactivar horario cancela reservas futuras del horario.
- No se eliminan silenciosamente datos historicos utiles.

## Logros

- "Mis logros" solo aparece en el portal si existen logros.
- Los logros pueden estar `logrado` o en progreso.

## Notificaciones

- Hay notificaciones para admin y alumnas.
- Usan soft-delete por contexto.
- La campanita de alumna muestra solo pendientes.
- La X en campanita marca vista y remueve del dropdown.
- El panel de notificaciones conserva el registro marcado como vista.

## Pagos

- Pagos son mensuales.
- No se manejan montos ni moneda.
- Hay estado de gracia antes de bloquear reservas.

## Updater

- `staging` usa versiones `rc`.
- `stable` usa versiones finales.
- `push` a `main` no publica automaticamente.
- GitHub Actions publican staging/stable.
- Token se guarda en WordPress, no en Git.

## Datos demo

- Existen datos de prueba para testing local.
- El seed debe ser idempotente.
- El boton de datos demo vive en Configuracion.

## Uninstall

- Desactivar plugin conserva datos.
- Eliminar/desinstalar plugin borra tablas, opciones, roles y capabilities.

## Seguridad

- El portal debe validar ownership.
- Admin debe validar `tp_manage_pilates`.
- Los mensajes de auth deben evitar user enumeration.
- SQL debe usar `$wpdb->prepare()` o metodos tipados.
