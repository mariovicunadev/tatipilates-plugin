# PROGRESS.md

## Última sesión
**Fecha:** 2026-07-07
**Qué se hizo:** Se promovió `1.2.3` al canal `stable` para live desde el workflow `Promote stable release`. El release final, tag `v1.2.3`, asset `tatipilates-1.2.3.zip`, manifest stable y version interna del ZIP quedaron verificados.

## Próximo paso inmediato
- En WordPress live, forzar comprobacion de updates, instalar `1.2.3` y confirmar que el rol Tatiana ve datos medicos mientras Admin Pilates no.

## Dudas / bloqueos abiertos
- AUD-02 Entrega 2 permanece bloqueada hasta definir la gestión y custodia de `TP_DATA_ENCRYPTION_KEY`.

## Historial
(Resumen cronológico extraído del CHANGELOG.md — ver ese archivo para detalle completo)

- [2026-07-07, 1.2.3] - Se publico stable con backups privados, importacion estricta, PAT del updater en servidor, demo seed restringido, capability medica separada y rol Tatiana con acceso medico.
- [2026-07-03, 1.2.3-rc.3] - Se preparo y valido el rol Tatiana con gestion operativa y acceso medico explicito.
- [2026-07-02, 1.2.3] - Los campos medicos quedaron bajo una capability separada y dejaron de recuperarse en listados o perfiles operativos.
- [2026-07-02, 1.2.3] - El PAT del updater paso a configuracion del servidor, con fallback legacy transitorio y limpieza automatica de la copia en base de datos.
- [2026-07-02, 1.2.3] - Los backups incorporaron contrato v2, validación estricta previa, límites y pruebas de rollback/formato futuro.
- [2026-07-02, 1.2.3] - El seeder demo quedo restringido a entornos autorizados y las cuentas demo de produccion se endurecen sin borrar sus datos.
- [2026-07-01, 1.2.3] — Se preparó la invalidación del manifiesto privado cuando WordPress fuerza una comprobación de plugins.
- [2026-07-01, 1.2.3] — Los backups automáticos pasaron a almacenamiento privado fuera del webroot, con migración legacy y alertas de fallo.
- [2026-06-18, 1.2.2] — Se corrigió la alineación de las opciones de Recordatorios en Configuración.
- [2026-06-18, 1.2.1] — El updater privado se migró al repositorio canónico `mariovicunadev/tatipilates-plugin`.
- [2026-06-18, 1.2.0] — Se incorporaron backups JSON, desinstalación conservadora, rediseño integral, acciones de notificaciones sin recarga y navegación semanal parcial.
- [2026-06-06, 1.1.2] — Se añadió el paquete de documentación operativa y técnica.
- [2026-06-05, 1.1.0] — Se implementó el updater privado con canales staging/stable y GitHub Actions.
- [2026-06-05, 1.0.1] — Se mejoró el portal mobile, la campanita y la presentación de reservas/logros/ausencias.
- [2026-06-03, 1.0.0] — Se publicó la versión inicial con portal, reservas, pagos, asistencia, recuperaciones, notificaciones, PWA y datos demo.
