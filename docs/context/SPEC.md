# SPEC.md

## Requisitos funcionales
- [x] RF1: Administrar horarios, modalidades y cupos desde wp-admin.
- [x] RF2: Registrar estudiantes vinculados a usuarios nativos de WordPress y asignarles un plan.
- [x] RF3: Permitir reservas administrativas y reservas de alumnas respetando cupos, plan semanal y estado de pago.
- [x] RF4: Gestionar cancelaciones, ausencias y créditos de recuperación con consistencia transaccional.
- [x] RF5: Registrar pagos mensuales sin montos ni moneda y bloquear reservas fuera del periodo de gracia.
- [x] RF6: Marcar asistencia o falta y generar recuperaciones cuando corresponda.
- [x] RF7: Mostrar agenda semanal privada para administración y alumnas autorizadas.
- [x] RF8: Proveer login, recuperación de contraseña y dashboard dentro de `/mi-pilates`.
- [x] RF9: Crear, marcar y eliminar notificaciones internas para admin y alumnas sin recargas completas cuando aplica.
- [x] RF10: Funcionar como PWA ligera con manifest, service worker, página offline e iconos.
- [x] RF11: Restringir administración mediante `tp_manage_pilates`; Admin Pilates opera sin datos médicos y Tatiana agrega `tp_view_medical_data`.
- [x] RF12: Exportar/importar backups JSON del plugin y conservar datos al desinstalar por defecto.
- [x] RF13: Distribuir versiones privadas por canales `staging` y `stable` mediante GitHub Releases.
- [x] RF14: Separar el acceso a campos médicos mediante `tp_view_medical_data` sin exponerlos en listados operativos.

## Requisitos no funcionales
- Performance: evitar recargas completas innecesarias; usar índices documentados; mantener consultas acotadas y assets versionados.
- Seguridad: nonces, capabilities, ownership, sanitización, escaping, SQL preparado, tokens fuera de Git y mensajes de autenticación sin enumeración.
- Escalabilidad: lógica de dominio fuera de vistas, helpers centralizados, migraciones idempotentes y releases reversibles mediante ZIP anterior.

## Criterios de aceptación
| Feature | Criterio |
|---|---|
| Reservas | No permite duplicados, sobrecupo ni exceder el plan desde el portal; una recuperación usada queda vinculada de forma atómica. |
| Pagos | Una alumna sin pago puede reservar solo durante la gracia; fuera de ella queda bloqueada. |
| Ausencias | Reportar una ausencia propia actualiza la reserva y genera como máximo una recuperación válida. |
| Portal | Solo alumnas activas acceden a datos privados y todas las acciones validan ownership. |
| Administración | Solo usuarios con `tp_manage_pilates` ejecutan acciones administrativas y los campos médicos exigen además `tp_view_medical_data`. |
| Notificaciones | Visto y soft-delete son independientes para admin y alumna; las acciones mejoradas no recargan toda la página. |
| PWA/mobile | No hay overflow horizontal y los assets actualizados no quedan retenidos indefinidamente. |
| Backups | Exporta datos propios sin contraseñas; la importación es idempotente y mantiene relaciones. |
| Updater | Staging recibe solo RC, live recibe solo versiones finales y los ZIP privados se descargan con token autorizado. |
| Release | `./pre-release-check.sh --yes` pasa antes de publicar y stable solo se promueve después de probar staging. |

## Fuera de alcance
- Cobros, montos, moneda, facturación o pasarelas de pago.
- Marketplace público o distribución por WordPress.org.
- Aplicación móvil nativa.
- Waitlist, integración de calendario, reportes mensuales y perfil editable por alumnas hasta que entren al roadmap activo.
- Sustituir los backups completos del hosting con el backup JSON del plugin.
