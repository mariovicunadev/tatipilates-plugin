# TASKS.md

## En progreso
- [~] Confirmar instalación y smoke test final de `1.2.3-rc.2` en staging.

## Pendientes
- [ ] Promover el patch a stable solo después de confirmar detección, descarga e instalación en staging.
- [ ] Agregar diagnóstico del updater: canal, versión instalada/disponible, último error y “Comprobar ahora”.
- [ ] Crear pruebas automatizadas básicas para helpers, reservas y reglas transaccionales.
- [ ] Automatizar minificación de CSS y lint de JS/CSS/YAML en pre-release.
- [ ] Añadir smoke tests de portal y PWA en mobile.
- [ ] Mejorar emails y logs administrativos de fallos de envío.
- [ ] Mejorar historial y restauración selectiva de backups.

## Hechas
- [x] Declarar WordPress 7.0 como versión probada en manifests de staging y stable.
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
- [ ] Implementar AUD-02 Entrega 2 (razón del bloqueo: falta definir la gestión y custodia de `TP_DATA_ENCRYPTION_KEY`).
