# Security checklist

Checklist recurrente antes de publicar cambios sensibles.

## Entradas y permisos

- Toda accion admin verifica nonce.
- Toda accion admin verifica `current_user_can(TP_Roles::CAP_MANAGE_PILATES)`.
- Toda accion de alumna verifica `is_user_logged_in()`.
- Toda accion de alumna verifica ownership por `alumna_id`.
- Nunca confiar en `alumna_id`, `reserva_id`, `recuperacion_id` enviados desde forms sin validar ownership.

## AJAX y admin-post

- Handlers retornan JSON si son AJAX.
- Handlers `admin_post_*` redirigen con `wp_safe_redirect()` y `exit`.
- Fallas de autorizacion usan 403 cuando corresponde.
- Nonces se validan antes de ejecutar logica.

## SQL

- Usar `$wpdb->prepare()` en SELECT/queries con variables.
- Usar `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()` para operaciones simples.
- Usar formatos `%d`, `%s`, `%f` correctos.
- No hardcodear `wp_`; usar `$wpdb->prefix`.
- Verificar `false === $resultado` en insert/update/delete.
- Loguear errores DB con `TP_Helpers::log_db_error()` o `tp_log()`.

## Sanitizacion al guardar

- Strings: `sanitize_text_field()`.
- Textareas: `sanitize_textarea_field()` o `wp_kses_post()` si se permite HTML.
- Emails: `sanitize_email()`.
- Enteros: `absint()` o `intval()`.
- URLs: `esc_url_raw()`.
- Keys: `sanitize_key()`.

## Escape al mostrar

- Texto HTML: `esc_html()`.
- Atributos: `esc_attr()`.
- URLs: `esc_url()`.
- JS inline: `esc_js()`.
- Textareas: `esc_textarea()`.

## Autenticacion

- Login del portal usa `wp_signon()`.
- Mensaje de login fallido es generico.
- Reset de contrasena no revela si el correo existe.
- Reset usa sistema nativo de WordPress.
- Rate limiting activo en login.
- Logout usa `wp_logout_url()`.

## Updater privado

- Nunca hardcodear tokens.
- Token se guarda solo en WordPress.
- Token fine-grained con acceso solo al repo.
- Permiso minimo: `Contents: Read-only`.
- No pegar tokens en chats, issues, commits ni capturas.
- Si un token se expone, revocarlo y generar uno nuevo.

## Datos sensibles

- `CONTEXTO.md` no se sube.
- `guia-rapida-tatiana.*` no se sube.
- `checklist-pruebas-tati-pilates.*` no se sube.
- `release/` no se sube.
- `dev/` no se sube.
- No commitear credenciales SMTP, GitHub tokens ni usuarios reales.

## Transacciones

Usar transacciones cuando se tocan varias tablas:

- reservar con recuperacion;
- cancelar reserva con recuperacion;
- marcar ausencia y crear recuperacion;
- resetear estudiante;
- desactivar alumna;
- desactivar horario.

## Logging

- No mostrar `$wpdb->last_error` al usuario.
- No loguear contrasenas ni tokens.
- Evitar logs con queries completas si incluyen datos personales.
- Usar `tp_log()` solo con contexto minimo necesario.

## Pre-release security quick pass

Antes de publicar cambios de seguridad:

```zsh
rg -n "wp_ajax_|admin_post_|current_user_can|check_admin_referer|check_ajax_referer|wp_verify_nonce" admin includes public
rg -n "\\$wpdb->query|\\$wpdb->prepare|\\$wpdb->insert|\\$wpdb->update|\\$wpdb->delete" admin includes public
rg -n "echo \\$_|print_r\\(|var_dump\\(|die\\(|exit\\(" admin includes public
```
