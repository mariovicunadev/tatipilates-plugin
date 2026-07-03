# PROGRESS.md

## Última sesión
**Fecha:** 2026-07-03
**Qué se hizo:** Staging completó el checklist de `1.2.3-rc.1` y detectó `1.2.3-rc.2` mediante el updater nuevo con `TP_GITHUB_TOKEN`. Se corrigió la metadata de compatibilidad para declarar WordPress 7.0 como versión probada en staging y futuras promociones stable.

## Próximo paso inmediato
- Confirmar la instalación de `1.2.3-rc.2` en staging y promover `1.2.3` a stable cuando la validación quede cerrada.

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
