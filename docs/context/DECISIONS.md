# DECISIONS.md
(Registro de decisiones técnicas — ADR simplificado)

## [2026-06-03] Construir sobre WordPress y usuarios nativos
**Decisión:** Implementar el sistema como plugin privado y vincular cada alumna con `wp_users`.
**Alternativas consideradas:** Aplicación independiente; autenticación y base de usuarios propias.
**Por qué se eligió esta:** Reutiliza administración, sesiones, roles, correo y base de datos ya operativos en el sitio.
**Estado:** Vigente

---

## [2026-06-03] Mantener el portal dentro de `/mi-pilates`
**Decisión:** Login, reset, dashboard, reservas y PWA viven dentro del portal del plugin.
**Alternativas consideradas:** Usar `wp-login.php`; crear una aplicación móvil separada.
**Por qué se eligió esta:** Mantiene una experiencia controlada para alumnas y evita exponer superficies administrativas de WordPress.
**Estado:** Vigente

---

## [2026-06-05] Distribuir releases privados por dos canales
**Decisión:** `staging` consume versiones `-rc.N` y `stable` consume versiones finales mediante `updates.json` y GitHub Releases.
**Alternativas consideradas:** Subir ZIP manualmente siempre; desplegar cada push a `main`; usar un único canal.
**Por qué se eligió esta:** Separa validación y producción, conserva rollback por release y evita publicar cambios sin probar.
**Estado:** Vigente

---

## [2026-06-18] Conservar datos al desinstalar por defecto
**Decisión:** `uninstall.php` solo elimina tablas, opciones y roles cuando Zona peligrosa activa explícitamente `tp_delete_data_on_uninstall`.
**Alternativas consideradas:** Borrar siempre; no ofrecer limpieza automática.
**Por qué se eligió esta:** Reduce el riesgo de pérdida accidental en staging y live sin impedir una limpieza controlada.
**Estado:** Vigente

---

## [2026-06-18] Incorporar backups JSON propios
**Decisión:** Exportar tablas `tp_*`, usuarios vinculados sin contraseñas y configuración; importar de forma idempotente.
**Alternativas consideradas:** Depender únicamente del hosting; exportar SQL completo.
**Por qué se eligió esta:** Facilita respaldo y migración del dominio del plugin sin incluir toda la instalación de WordPress.
**Estado:** Vigente

---

## [2026-06-18] Mejorar interacciones sin recargar toda la página
**Decisión:** Notificaciones admin/portal y navegación semanal mejorada usan respuestas parciales o JSON, con fallback a formularios/redirecciones.
**Alternativas consideradas:** Mantener navegación y acciones con recarga completa; convertir el plugin en SPA.
**Por qué se eligió esta:** Mejora percepción de velocidad sin reemplazar la arquitectura WordPress ni perder degradación progresiva.
**Estado:** Vigente

---

## [2026-06-18] Centralizar el cálculo del cupo semanal
**Decisión:** `TP_Reservas::cupo_plan_semana()` es la fuente de verdad para límites, uso y clases restantes.
**Alternativas consideradas:** Repetir cálculos en admin y portal.
**Por qué se eligió esta:** Evita discrepancias entre interfaces y concentra reglas especiales como plan individual y plan `5x`.
**Estado:** Vigente

---

## [2026-06-18] Canonizar el repositorio privado nuevo
**Decisión:** Updater, manifiesto y documentación apuntan a `mariovicunadev/tatipilates-plugin`.
**Alternativas consideradas:** Depender del redirect del repositorio anterior; mantener un repositorio puente.
**Por qué se eligió esta:** Los assets privados requieren que la autorización coincida con la URL canónica devuelta por GitHub.
**Estado:** Vigente

---

## [2026-07-01] Invalidar el manifiesto privado al forzar updates
**Decisión:** Conectar `delete_site_transient_update_plugins` con la limpieza de `tp_updater_manifest`.
**Alternativas consideradas:** Esperar el TTL de 30 minutos; pedir guardar la configuración manualmente.
**Por qué se eligió esta:** Hace que “Comprobar de nuevo” refresque también el caché privado y muestre releases recientes inmediatamente.
**Estado:** Vigente en local; pendiente de publicación

---

## [2026-07-01] Guardar backups fuera del webroot
**Decisión:** Guardar los backups automáticos en una ruta privada fuera de `ABSPATH`, configurable mediante `TP_BACKUP_DIR`, y servir archivos guardados solo mediante un handler administrativo autenticado.
**Alternativas consideradas:** Mantenerlos cifrados dentro de uploads; depender de `.htaccess` o reglas específicas de Nginx.
**Por qué se eligió esta:** Evita exposición estática sin cambiar el formato JSON ni depender del servidor web, y mantiene portabilidad y restauración de backups existentes.
**Estado:** Vigente en local; pendiente de publicación

---

## [2026-07-02] Limitar y endurecer cuentas demo por entorno
**Decisión:** Permitir el seeder solo en `local` o `development` con `TP_ALLOW_DEMO_DATA=true`, generar credenciales aleatorias en cada ejecución y rotar cuentas demo detectadas durante upgrades de producción.
**Alternativas consideradas:** Conservar contraseñas fijas; ocultar únicamente el botón; eliminar automáticamente todos los datos demo en producción.
**Por qué se eligió esta:** Bloquea la ejecución en el dominio y el handler, elimina credenciales conocidas y neutraliza accesos existentes sin borrar información que requiera revisión humana.
**Estado:** Vigente en local; pendiente de publicación

---

## [2026-07-02] Versionar y validar backups antes de mutar datos
**Decisión:** Definir `format_version: 2`, normalizar legacy v1 y ejecutar un contrato estricto completo antes de abrir la transacción de importación.
**Alternativas consideradas:** Confiar en restricciones SQL; validar cada fila mientras se inserta; aceptar campos desconocidos para máxima flexibilidad.
**Por qué se eligió esta:** Evita importaciones parciales, coerciones silenciosas y payloads futuros incompatibles, manteniendo soporte explícito para los backups existentes.
**Estado:** Vigente en local; pendiente de publicación

---

## [2026-07-02] Custodiar el PAT del updater fuera de WordPress
**Decisión:** Resolver el PAT mediante `TP_GITHUB_TOKEN`, con precedencia de constante sobre entorno, y conservar el valor de `wp_options` solo como fallback durante una version de transicion.
**Alternativas consideradas:** Mantener el PAT en `wp_options`; cifrarlo con una clave gestionada por el mismo WordPress; exigir un corte inmediato sin fallback.
**Por qué se eligió esta:** Reduce la exposicion ante volcados de base de datos, permite custodia operativa en el servidor y evita interrumpir actualizaciones existentes durante la migracion.
**Estado:** Vigente en local; pendiente de publicación

---

## [2026-07-02] Separar el acceso medico de la operacion administrativa
**Decisión:** Crear `tp_view_medical_data`, asignarla por defecto solo a `administrator` y permitir autorizaciones individuales para usuarios Admin Pilates que realmente la necesiten.
**Alternativas consideradas:** Mantener todo bajo `tp_manage_pilates`; dar la capability al rol Admin Pilates completo; ocultar campos solo en la interfaz.
**Por qué se eligió esta:** Aplica menor privilegio, evita que las tareas de agenda, pagos o asistencia impliquen acceso de salud y mantiene una via explicita para personal autorizado.
**Estado:** Vigente en local; pendiente de publicación

---
