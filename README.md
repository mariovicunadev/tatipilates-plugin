# Tati Pilates Plugin

Plugin privado de WordPress para administrar clases, alumnas, reservas, pagos, recuperaciones, asistencia y portal de estudiantes de Tati Pilates.

Este repositorio contiene solo el codigo del plugin. No incluye credenciales, documentacion sensible, datos locales ni paquetes ZIP generados.

## Funcionalidades principales

- Administracion de horarios, cupos y modalidades.
- Registro de alumnas vinculadas a usuarios nativos de WordPress.
- Planes semanales e individuales.
- Reservas desde wp-admin y desde el portal `/mi-pilates`.
- Cancelacion de reservas, reporte de ausencias y recuperaciones.
- Agenda semanal privada y copia de agenda para WhatsApp.
- Pagos mensuales simples, sin montos ni moneda.
- Asistencia, faltas y generacion automatica de recuperaciones.
- Logros personales, datos medicos, alergias, cumpleanos y fecha de inicio.
- Notificaciones internas para admin y alumnas.
- PWA ligera del portal de alumnas.
- Datos de prueba para entornos locales.
- Rol limitado `tp_admin_pilates` con capability `tp_manage_pilates`.

## Estructura

```text
tatipilates.php
uninstall.php
includes/
admin/
public/
assets/
docs/
.github/workflows/
updates.json
pre-release-check.sh
```

Archivos locales excluidos del repo:

- `CONTEXTO.md`
- `dev/`
- `release/`
- `guia-rapida-tatiana.*`
- `checklist-pruebas-tati-pilates.*`
- `mipilates.zip`
- logs y archivos de Mac

## Desarrollo local

En LocalWP, el plugin puede montarse con un symlink hacia la carpeta del proyecto:

```zsh
ln -s "/Users/vicunav/Documents/Codex/Mi Pilates Admin" "/ruta/al/wordpress/wp-content/plugins/tatipilates"
```

Luego activar el plugin desde wp-admin.

El portal de alumnas vive en:

```text
/mi-pilates
```

## Pre-release

Antes de generar un ZIP para staging o live, correr:

```zsh
./pre-release-check.sh
```

Para correrlo sin preguntas interactivas:

```zsh
./pre-release-check.sh --yes
```

El script verifica:

- Sintaxis PHP con el PHP de LocalWP.
- Archivos PHP con BOM UTF-8.
- Codigo de debug olvidado.
- Que no exista `release/tatipilates/`.
- Version del plugin en `tatipilates.php`.

Si todo pasa, genera un ZIP en:

```text
release/tatipilates-VERSION-YYYYMMDD-HHMM.zip
```

El ZIP incluye:

- `tatipilates.php`
- `uninstall.php`
- `includes/`
- `admin/`
- `public/`
- `assets/`

El ZIP no incluye documentacion local, credenciales, `dev/`, `release/`, dotfiles ni archivos auxiliares.

## Workflow recomendado

1. Crear o cambiar una feature en local.
2. Probar en LocalWP.
3. Revisar cambios:

```zsh
git status
git diff
```

4. Registrar el cambio en `CHANGELOG.md`, normalmente en la version `Unreleased`.
5. Si el cambio se va a mandar a staging, actualizar la version en `tatipilates.php`.
6. Correr pre-release:

```zsh
./pre-release-check.sh --yes
```

7. Si los checks pasan, commitear:

```zsh
git add .
git commit -m "tipo: descripcion corta"
git push
```

Ejemplos de mensajes:

```text
feat: add student notification preferences
fix: prevent duplicate recovery booking
chore: update release checks
```

8. Subir el ZIP generado a staging.
9. Probar staging, especialmente:

- Login y reset de contrasena en `/mi-pilates`.
- Dashboard de alumna.
- Reservas, cancelaciones y recuperaciones.
- Agenda semanal.
- Pagos, asistencia y notificaciones.
- PWA/offline.
- Admin Pilates con permisos limitados.

10. Si staging esta correcto, subir el mismo ZIP a live.
11. Despues del deploy live, crear un tag de version:

```zsh
git tag v1.0.1
git push origin v1.0.1
```

12. Si aparece un bug en staging o live, corregirlo en local, generar un nuevo ZIP y volver a probar. No editar directo en staging/live sin replicar el cambio en Git.

## Reglas importantes

- No subir credenciales ni `CONTEXTO.md` al repo.
- No subir ZIPs generados.
- No subir `dev/`.
- No editar directamente el plugin en staging/live sin replicar el cambio en este repo.
- Si se cambia estructura de base de datos, actualizar activacion/migracion y probar en staging antes de live.
- Si se cambia seguridad o permisos, probar con admin, Admin Pilates y alumna.

## Deploy a staging y live

El flujo manual tradicional es:

```text
LocalWP -> pre-release ZIP -> staging -> pruebas -> live
```

Para staging, subir el ZIP generado por `pre-release-check.sh` desde wp-admin o por el mecanismo de deploy disponible.

Para live, usar el mismo ZIP que ya paso staging. Si se necesita corregir algo despues de staging, generar un nuevo ZIP, volver a probar y recien despues publicar.

## Updater privado

Desde `1.1.0`, el plugin incluye un updater privado por canales:

- `staging`: para el sitio de pruebas.
- `stable`: para el sitio live.

La primera version que contiene el updater debe instalarse manualmente con ZIP normal. Despues de ese bootstrap, WordPress puede detectar actualizaciones privadas desde:

```text
Plugins > Tati Pilates > Actualizar ahora
```

Configuracion en cada sitio:

```text
Tati Pilates > Configuracion > Actualizaciones privadas
```

En staging:

```text
Canal: staging
```

En live:

```text
Canal: stable
```

El token de GitHub se guarda solo en WordPress. No se sube al repo.

Flujo nuevo recomendado:

```text
Local changes
-> commit/push main
-> GitHub Action: Publish staging release 1.1.1-rc.1
-> staging actualiza desde WordPress
-> pruebas
-> GitHub Action: Promote stable release 1.1.1
-> live actualiza desde WordPress
```

Documentacion completa:

```text
docs/updater-workflow.md
```

## Guias operativas

- `docs/README.md`: indice de documentacion tecnica y operativa.
- `docs/architecture.md`: mapa de clases, capas y responsabilidades.
- `docs/codex-workflow-guide.md`: como abrir chats nuevos, pedir cambios, versionar y documentar.
- `docs/codex-workflow-guide.pdf`: version imprimible/rapida de la guia de trabajo con Codex.
- `docs/data-model.md`: tablas propias, relaciones, indices y reglas de datos.
- `docs/local-development.md`: LocalWP, symlink, PHP local, datos demo y flujo local.
- `docs/product-decisions.md`: decisiones funcionales que deben respetarse.
- `docs/release-runbook.md`: pasos para publicar a staging y promover a live.
- `docs/roadmap.md`: mejoras futuras y deuda tecnica.
- `docs/security-checklist.md`: checklist de seguridad para cambios sensibles.
- `docs/testing-checklist.md`: pruebas antes de publicar una version.
- `docs/troubleshooting.md`: problemas frecuentes y diagnostico.
- `docs/updater-workflow.md`: releases por canales `staging` y `stable`.

## Seguridad

El plugin usa:

- Nonces en acciones sensibles.
- Capability `tp_manage_pilates` para administracion.
- Ownership checks para acciones de alumnas.
- Sanitizacion al guardar y escaping al mostrar.
- `$wpdb->prepare()` y metodos tipados de `$wpdb`.
- Checks estrictos `false ===` en operaciones de base de datos.
- Logging interno controlado por `WP_DEBUG`.
- Rate limiting en login del portal.
- Mensajes genericos en reset de contrasena para evitar user enumeration.
- Updater privado sin tokens hardcodeados en el codigo.

## Repositorio

Repositorio privado:

```text
https://github.com/wefefino/tatipilates-plugin
```

## Prompt base para nuevos chats

Usar este prompt al abrir una nueva conversacion de Codex para cambios futuros:

```text
Estamos trabajando en el repo local:
/Users/vicunav/Documents/Codex/Mi Pilates Admin

Plugin privado de WordPress: Tati Pilates.
Repo GitHub privado: https://github.com/wefefino/tatipilates-plugin
Branch principal: main.

Lee README.md, CHANGELOG.md y docs/updater-workflow.md antes de tocar codigo.
Si existe CONTEXTO.md en local, leelo tambien, pero no lo subas al repo.

Workflow:
- No hagas commit ni push hasta que yo lo pida.
- Registra cambios relevantes en CHANGELOG.md.
- Mantene fuera del repo credenciales, release/, dev/, CONTEXTO.md y ZIPs.
- Antes de cerrar una tanda para staging, correr ./pre-release-check.sh --yes.
- Para publicar updates, usar los canales staging/stable documentados en docs/updater-workflow.md.

Objetivo de este chat:
[describir aqui el cambio o bug]
```
