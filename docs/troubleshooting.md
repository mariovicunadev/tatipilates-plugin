# Troubleshooting

Problemas frecuentes y como diagnosticarlos.

## No llega email

Probables causas:

- WordPress no tiene SMTP configurado.
- Remitente no esta autorizado por SPF/DKIM.
- Hosting bloquea `wp_mail()`.
- Email fue a spam.

Pasos:

1. Instalar/configurar SMTP.
2. Recomendado: FluentSMTP + Brevo.
3. From email: `hola@tatipilates.com`.
4. Probar email desde el plugin SMTP.
5. Crear una alumna nueva y revisar bloque "Acceso temporal creado".

Si el plugin muestra credenciales, la alumna puede entrar aunque el mail falle.

## No aparece update en staging/live

Verificar:

- Updater privado activo.
- Canal correcto:
  - staging: `staging`
  - live: `stable`
- Token guardado.
- Token con permiso `Contents: Read-only`.
- `updates.json` apunta a una version mayor que la instalada.
- GitHub release existe.
- El release tiene asset ZIP con nombre exacto.

Forzar refresh:

```text
Tati Pilates > Configuracion > Actualizaciones privadas > Guardar updater
Escritorio > Actualizaciones > Comprobar de nuevo
```

## Live ve una RC

Live no deberia ver `-rc`.

Revisar:

- live tiene canal `stable`, no `staging`;
- `updates.json` stable apunta a version final;
- cache de updates fue refrescada.

## Staging no ve RC

Revisar:

- staging tiene canal `staging`;
- RC fue publicada con `Publish staging release`;
- tag existe;
- prerelease tiene ZIP;
- version instalada es menor que RC.

## GitHub token invalido

Sintomas:

- no aparece update;
- no se puede descargar ZIP;
- al guardar updater no hay error visible pero no detecta release.

Solucion:

- revocar token viejo si fue expuesto;
- crear fine-grained token;
- repo: `wefefino/tatipilates-plugin`;
- permiso: `Contents: Read-only`;
- pegarlo de nuevo en WordPress.

## PWA muestra CSS viejo

Pasos:

- Probar en navegador normal primero.
- Recargar fuerte.
- Limpiar datos del sitio.
- En iOS, eliminar app instalada y volver a abrir `/mi-pilates`.
- Confirmar que `assets/css/portal.css` cambio.
- Confirmar que service worker no cachea indefinidamente HTML viejo.

## Vista mobile rota

Revisar:

- `assets/css/portal.css`;
- `assets/css/tatipilates-portal.min.css`;
- media query `max-width: 640px`;
- textos largos en botones;
- dropdowns con `position:absolute`.

Validar en:

- iPhone Safari;
- Chrome mobile;
- ancho estrecho en desktop devtools.

## Errores PHP despues de update

Pasos:

```zsh
./pre-release-check.sh --yes
```

En servidor:

- activar `WP_DEBUG_LOG`;
- revisar `wp-content/debug.log`;
- buscar `[TatiPilates]`.

## No se puede reservar

Revisar:

- alumna activa;
- plan no individual;
- pago del mes;
- periodo de gracia;
- cupos disponibles;
- limite semanal;
- reservas existentes;
- recuperaciones disponibles si aplica.

## No se puede cancelar reserva

Revisar:

- reserva pertenece a la alumna logueada;
- fecha futura;
- estado `reservada`;
- nonce valido.

## Agenda no abre

Revisar:

- `vista=agenda`.
- Alumna logueada puede ver agenda.
- Usuario no alumna debe tener `tp_manage_pilates`.

## Datos demo no se generan

Revisar:

- usuario actual tiene `tp_manage_pilates`;
- `TP_Test_Data` existe;
- no hay errores DB;
- revisar `WP_DEBUG_LOG`.

## Workflows de GitHub fallan

Revisar:

```zsh
gh run list --repo wefefino/tatipilates-plugin --limit 5
gh run view RUN_ID --repo wefefino/tatipilates-plugin --log
```

Errores comunes:

- version staging sin `-rc.N`;
- version stable con `-rc`;
- tag ya existe;
- release ya existe;
- asset con nombre duplicado.
