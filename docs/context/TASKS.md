# TASKS.md

## En progreso
- Ninguna tarea activa.

## Pendientes
- Ninguna tarea pendiente.

## Hechas
- [x] Confirmar `1.3.1` instalado desde el updater en live.
- [x] Bindear en Elementor los seis widgets Heading de `#precios` (ref. y USD) al Dynamic Tag `tp-precio`.
- [x] Publicar y verificar `1.3.1-rc.1` en staging y promover `1.3.1` a stable con tags, manifiestos, hashes y contenido interno de los ZIP correctos.
- [x] Corregir la validacion estricta de precios, el reporte de fallos de persistencia y la purga de cache; ampliar la regresion a 13 checks de precios y validar la suite completa en LocalWP.
- [x] Publicar `1.3.0-rc.1` en staging y promover `1.3.0` a stable con la gestion de precios probada.
- [x] Implementar y validar localmente `TP_Precios`: opcion `tp_precios_config`, pantalla admin `Precios` con precio de referencia y precio de efectivo USD manual por plan/clase, Dynamic Tag de Elementor `tp-precio` (controles Plan/Tipo) y purga de cache SG Optimizer al guardar. Pruebas CLI en `tests/pricing-config.php` (13 checks).
- [x] Agregar lint JS/CSS/YAML al pre-release sin minificar assets del plugin.
- [x] Añadir smoke test HTTP opcional de portal/PWA mobile mediante `TP_PORTAL_URL`.
- [x] Registrar fallos de email como eventos operativos visibles para administracion.
- [x] Mejorar backups con historial privado, descarga por archivo y restauracion/importacion selectiva por tabla.
- [x] Publicar `1.2.6-rc.1` en staging y `1.2.6` en live con cifrado medico, release, manifest, tag, hash y version interna del ZIP verificados.
- [x] Implementar localmente AUD-02 Entrega 2 con cifrado Sodium, migracion idempotente, diagnostico admin y pruebas CLI.
- [x] Publicar `1.2.5-rc.1` en staging y `1.2.5` en live con release, manifest, tag, hash y version interna del ZIP verificados.
- [x] Crear pruebas automatizadas básicas para helpers, reservas y reglas transaccionales.
- [x] Agregar diagnóstico del updater: canal, versión instalada/disponible, último error y “Comprobar ahora”.
- [x] Publicar `1.2.4-rc.1` en staging y `1.2.4` en live con release, manifest, tag, hash y version interna del ZIP verificados.
- [x] Promover `1.2.3` a stable/live con el workflow de GitHub y verificar release, manifest, tag, hash y version interna del ZIP.
- [x] Instalar y validar `1.2.3-rc.3` en staging mediante el updater nuevo.
- [x] Crear el rol Tatiana con gestión operativa y acceso médico, manteniendo Admin Pilates sin acceso médico.
- [x] Automatizar la metadata `tested` desde la versión WordPress validada por el pre-release local.
- [x] Validar que el updater nuevo detecta `1.2.3-rc.2` con `TP_GITHUB_TOKEN`.
- [x] Publicar la tanda de auditoría y el fix de caché del updater como `1.2.3-rc.1`.
- [x] Invalidar el manifiesto privado cuando WordPress fuerza una comprobación de updates.
- [x] Resolver AUD-02 Entrega 1 con capability médica separada, consultas explícitas y preservación de datos en ediciones sin permiso.
- [x] Resolver AUD-08 moviendo el PAT del updater a configuración del servidor, con transición legacy y limpieza automática de `wp_options`.
- [x] Resolver AUD-03 con contrato versionado, allowlists estrictas, límites, preflight completo y rollback verificado.
- [x] Resolver AUD-04 restringiendo el seeder demo, rotando credenciales y retirando privilegios demo en producción.
- [x] Resolver AUD-01 moviendo backups fuera del webroot, migrando archivos legacy y alertando fallos automáticos por notificación y email.
- [x] Centralizar contexto operativo en `docs/context/`.
- [x] Publicar el rediseño de admin y portal, notificaciones sin recarga y backups en la serie `1.2.x`.
- [x] Migrar el updater al repositorio `mariovicunadev/tatipilates-plugin`.
- [x] Validar actualización privada autenticada en live.
- [x] Publicar `1.2.2` stable con la corrección visual de Recordatorios.

## Bloqueadas
- [ ] Implementar rotacion/exportacion controlada de `TP_DATA_ENCRYPTION_KEY` (razón del bloqueo: no es necesaria para operar ahora; requiere runbook operativo aparte).
