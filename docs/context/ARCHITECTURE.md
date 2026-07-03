# ARCHITECTURE.md

## Estructura de carpetas
```text
tatipilates.php                  # bootstrap, versión y autoload
uninstall.php                    # desinstalación conservadora
includes/                        # dominio y servicios
  class-activator.php
  class-alumnas.php
  class-asistencia.php
  class-backups.php
  class-helpers.php
  class-horarios.php
  class-notificaciones.php
  class-pagos.php
  class-recuperaciones.php
  class-reservas.php
  class-roles.php
  class-test-data.php
  class-updater.php
admin/
  class-admin.php                # menús, preparación y handlers admin
  views/                         # vistas wp-admin
public/
  class-portal.php               # portal, auth, acciones y PWA
  views/portal.php
assets/
  css/admin.css
  css/portal.css
  css/tatipilates-portal.min.css
  js/portal.js
  icons/
docs/                            # documentación técnica y operativa
  context/                       # contexto mínimo para chats nuevos
.github/workflows/               # publicación staging/stable
updates.json                     # manifiesto de canales
pre-release-check.sh             # validación y empaquetado
release-metadata.json            # compatibilidad validada para releases
```

## Patrón arquitectónico
Plugin modular por capas, inspirado en MVC pero no MVC estricto:

- `includes/` contiene dominio, persistencia y servicios.
- `admin/class-admin.php` y `public/class-portal.php` actúan como controladores/adaptadores de WordPress.
- `admin/views/` y `public/views/` renderizan datos preparados y no deben contener lógica de negocio ni consultas.
- `tatipilates.php` funciona como composition root: carga clases, registra hooks y conecta cron.

Se mantiene esta separación para reducir lógica duplicada, conservar las APIs nativas de WordPress y permitir cambios de UI sin alterar reglas de dominio.

## Entidades principales / Esquema de datos
- `wp_users`: identidad y autenticación; cada alumna enlaza mediante `wp_user_id`.
- `tp_alumnas`: perfil, plan, estado y datos personales/médicos.
- `tp_horarios`: día, hora, modalidad, cupo y estado.
- `tp_reservas`: relación alumna-horario-fecha, tipo y estado.
- `tp_recuperaciones`: créditos originados por ausencias y su uso/vencimiento.
- `tp_pagos`: confirmación mensual de pago, sin montos.
- `tp_milestones`: logros de alumnas.
- `tp_notificaciones`: avisos con visibilidad, visto y soft-delete por contexto.
- Opciones WordPress: versiones de schema, configuración de notificaciones, updater y política de desinstalación.

Reglas estructurales:

- Nunca asumir el prefijo `wp_`; usar `$wpdb->prefix`.
- `TP_Reservas::cupo_plan_semana()` es la fuente central del límite semanal.
- `tp_manage_pilates` habilita operacion administrativa a Admin Pilates y
  Tatiana; `tp_view_medical_data` se agrega solo a Tatiana y administrator para
  controlar la lectura y escritura de campos medicos.
- Operaciones que afectan varias tablas deben usar transacciones.
- `TP_Activator::ensure_schema()` mantiene instalaciones activas migradas.

## Integraciones externas
| Servicio | Uso | Auth |
|---|---|---|
| WordPress Core | Usuarios, roles, cron, mail, HTTP, opciones y actualizaciones | Sesión, nonces y capabilities |
| GitHub API/Releases | Manifiesto privado y descarga de ZIP | Fine-grained PAT, `Contents: Read-only` |
| GitHub Actions | Crear RC, tags, ZIP y releases stable | `GITHUB_TOKEN` del workflow |
| Navegador/PWA | Manifest, service worker, offline e instalación móvil | Sesión/cookies de WordPress |
| SMTP configurado en WordPress | Correos de credenciales y reset | Configuración externa del sitio |
