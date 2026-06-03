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
release/tatipilates-YYYYMMDD-HHMM.zip
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

4. Correr pre-release:

```zsh
./pre-release-check.sh --yes
```

5. Si los checks pasan, commitear:

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

6. Subir el ZIP generado a staging.
7. Probar staging, especialmente:

- Login y reset de contrasena en `/mi-pilates`.
- Dashboard de alumna.
- Reservas, cancelaciones y recuperaciones.
- Agenda semanal.
- Pagos, asistencia y notificaciones.
- PWA/offline.
- Admin Pilates con permisos limitados.

8. Si staging esta correcto, subir el mismo ZIP a live.
9. Despues del deploy live, guardar cualquier fix nuevo en Git con otro commit.

## Reglas importantes

- No subir credenciales ni `CONTEXTO.md` al repo.
- No subir ZIPs generados.
- No subir `dev/`.
- No editar directamente el plugin en staging/live sin replicar el cambio en este repo.
- Si se cambia estructura de base de datos, actualizar activacion/migracion y probar en staging antes de live.
- Si se cambia seguridad o permisos, probar con admin, Admin Pilates y alumna.

## Deploy a staging y live

El flujo esperado es:

```text
LocalWP -> pre-release ZIP -> staging -> pruebas -> live
```

Para staging, subir el ZIP generado por `pre-release-check.sh` desde wp-admin o por el mecanismo de deploy disponible.

Para live, usar el mismo ZIP que ya paso staging. Si se necesita corregir algo despues de staging, generar un nuevo ZIP, volver a probar y recien despues publicar.

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

## Repositorio

Repositorio privado:

```text
https://github.com/wefefino/tatipilates-plugin
```
