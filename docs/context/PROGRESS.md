# PROGRESS.md

## Última sesión
**Fecha:** 2026-07-02
**Qué se hizo:** Se cerró la tanda de seguridad formada por AUD-01, AUD-03, AUD-04, AUD-08 y AUD-02 Entrega 1, junto con la invalidación inmediata del caché privado del updater. La tanda se publicó como `1.2.3-rc.1` para validación en staging.

## Próximo paso inmediato
- Instalar `1.2.3-rc.1` en staging y ejecutar el checklist de backups, updater, datos demo, permisos médicos y regresión general antes de promover a stable.

## Dudas / bloqueos abiertos
- AUD-02 Entrega 2 permanece bloqueada hasta definir la gestión y custodia de `TP_DATA_ENCRYPTION_KEY`.
- Falta asignar número de versión al fix local del updater; por secuencia corresponde un patch posterior a `1.2.2`.

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
