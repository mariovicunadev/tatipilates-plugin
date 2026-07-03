# PROGRESS.md

## Última sesión
**Fecha:** 2026-07-03
**Qué se hizo:** Se preparó `1.2.3-rc.3` con `tp_tatiana`, la misma operación que Admin Pilates y acceso médico explícito. Admin Pilates permanece sin `tp_view_medical_data`; pasaron las pruebas de permisos (9/9), updater (6/6), backups (14/14) y el pre-release completo.

## Próximo paso inmediato
- Instalar `1.2.3-rc.3` en staging mediante el updater y validar Tatiana, Admin Pilates y administrator antes de promover a stable.

## Dudas / bloqueos abiertos
- AUD-02 Entrega 2 permanece bloqueada hasta definir la gestión y custodia de `TP_DATA_ENCRYPTION_KEY`.

## Historial
(Resumen cronológico extraído del CHANGELOG.md — ver ese archivo para detalle completo)

- [2026-07-02, Unreleased] - Los campos medicos quedaron bajo una capability separada y dejaron de recuperarse en listados o perfiles operativos.
- [2026-07-02, Unreleased] - El PAT del updater paso a configuracion del servidor, con fallback legacy transitorio y limpieza automatica de la copia en base de datos.
- [2026-07-02, Unreleased] - Los backups incorporaron contrato v2, validación estricta previa, límites y pruebas de rollback/formato futuro.
- [2026-07-02, Unreleased] - El seeder demo quedo restringido a entornos autorizados y las cuentas demo de produccion se endurecen sin borrar sus datos.
- [2026-07-01, Unreleased] — Se preparó la invalidación del manifiesto privado cuando WordPress fuerza una comprobación de plugins.
- [2026-07-01, Unreleased] — Los backups automáticos pasaron a almacenamiento privado fuera del webroot, con migración legacy y alertas de fallo.
- [2026-06-18, 1.2.2] — Se corrigió la alineación de las opciones de Recordatorios en Configuración.
- [2026-06-18, 1.2.1] — El updater privado se migró al repositorio canónico `mariovicunadev/tatipilates-plugin`.
- [2026-06-18, 1.2.0] — Se incorporaron backups JSON, desinstalación conservadora, rediseño integral, acciones de notificaciones sin recarga y navegación semanal parcial.
- [2026-06-06, 1.1.2] — Se añadió el paquete de documentación operativa y técnica.
- [2026-06-05, 1.1.0] — Se implementó el updater privado con canales staging/stable y GitHub Actions.
- [2026-06-05, 1.0.1] — Se mejoró el portal mobile, la campanita y la presentación de reservas/logros/ausencias.
- [2026-06-03, 1.0.0] — Se publicó la versión inicial con portal, reservas, pagos, asistencia, recuperaciones, notificaciones, PWA y datos demo.
