# PROGRESS.md

## Última sesión
**Fecha:** 2026-08-26
**Qué se hizo:** Se audito `1.3.0` y se corrigieron dos bordes de la gestion de precios: las entradas malformadas ya no se convierten silenciosamente y un fallo real de `update_option()` se informa como error sin purgar cache. Los avisos identifican plan y tipo, se corrigio el comentario obsoleto del hook de Elementor y se agrego una fuente estructurada con generador verificable para `CHANGELOG.md`. Pasaron 61 checks funcionales, lint estatico, el smoke portal/PWA, la importacion/rollback contra WordPress real y el pre-release completo en WordPress 7.1. Se publico `1.3.1-rc.1` en staging y `1.3.1` en stable; ambos tags, manifiestos, hashes y ZIP quedaron verificados contra `main`.

## Próximo paso inmediato
- Confirmar `1.3.1` instalado mediante el updater en live y completar el binding manual de los widgets Heading de Elementor.

## Dudas / bloqueos abiertos
- Mantener respaldadas las claves `TP_DATA_ENCRYPTION_KEY` por ambiente; sin la clave correcta, los campos medicos cifrados no son recuperables.
- La pantalla admin de Precios no se automatizo en navegador porque no habia una sesion autenticada disponible; la validacion HTTP y CLI de LocalWP si se completo.

## Historial
(Resumen cronologico operativo; ver `CHANGELOG.md` para el historial detallado publicado)

- [2026-08-26, 1.3.1] - Se endurecio la validacion de precios y el manejo de fallos de persistencia/cache; el changelog paso a generarse desde una fuente estructurada.
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
