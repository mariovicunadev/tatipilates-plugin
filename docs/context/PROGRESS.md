# PROGRESS.md

## Última sesión
**Fecha:** 2026-08-24
**Qué se hizo:** Se diseño y luego implemento localmente la gestion de precios: clase `TP_Precios` (opcion unica, sin tabla nueva), pantalla admin `Tati Pilates > Precios` con precio de referencia y precio de efectivo USD manual por cada uno de los seis planes/clases, Dynamic Tag de Elementor `tp-precio` (controles Plan/Tipo, registrado en `elementor/dynamic_tags/register`) y purga de cache SG Optimizer al guardar. Se corrigio un bug real de orden de carga de hooks (ver DECISIONS.md, 2026-08-24) que impedia que el tag se registrara. 12 checks en `tests/pricing-config.php`, todos en verde; `devtatipilates.local` validado visualmente por el usuario. La migracion de los widgets de Elementor existentes queda a cargo del usuario (no del agente).

## Próximo paso inmediato
- Publicar `-rc.1` a staging, probar con `docs/testing-checklist.md`, y promover a stable solo despues de confirmacion explicita del usuario.

## Dudas / bloqueos abiertos
- Mantener respaldadas las claves `TP_DATA_ENCRYPTION_KEY` por ambiente; sin la clave correcta, los campos medicos cifrados no son recuperables.

## Historial
(Resumen cronológico extraído del CHANGELOG.md — ver ese archivo para detalle completo)

- [2026-08-24, Unreleased] - Se agrego gestion de precios (referencia + efectivo USD manual) con pantalla admin dedicada y Dynamic Tag propio de Elementor.
- [2026-07-10, 1.2.6] - Se cifraron los campos medicos en reposo con clave de servidor, migracion idempotente y diagnostico administrativo.
- [2026-07-09, 1.2.5] - Se agrego diagnostico visible del updater privado y comprobacion manual desde Configuracion.
- [2026-07-09, 1.2.5] - Se agregaron pruebas CLI para helpers, reservas y rollbacks transaccionales.
- [2026-07-07, 1.2.4] - Se corrigio el aviso persistente de cuentas demo en live y el layout de Configuracion en dos columnas fluidas.
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
