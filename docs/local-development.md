# Local development

Guia para trabajar en la Mac con LocalWP.

## Ruta del repo

```text
/Users/vicunav/Documents/Codex/Mi Pilates Admin
```

## Sitio local

URL usada durante desarrollo:

```text
https://devtatipilates.local/
```

## Symlink del plugin

El plugin puede montarse en WordPress con un symlink:

```zsh
ln -s "/Users/vicunav/Documents/Codex/Mi Pilates Admin" "/ruta/al/wordpress/wp-content/plugins/tatipilates"
```

En esta Mac se uso:

```text
/Users/vicunav/Documents/wps/app/public/wp-content/plugins/tatipilates
```

## PHP de LocalWP

PHP usado por pre-release:

```text
/Users/vicunav/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin/bin/php
```

Lint manual:

```zsh
"/Users/vicunav/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin/bin/php" -l tatipilates.php
```

## Pre-release local

```zsh
./pre-release-check.sh --yes
```

Genera ZIP en:

```text
release/tatipilates-VERSION-YYYYMMDD-HHMM.zip
```

## Datos de prueba

Desde wp-admin:

```text
Tati Pilates > Configuracion > Datos de prueba
```

El seeder solo aparece cuando WordPress usa `local` o `development` y existe
este opt-in explicito en `wp-config.php`:

```php
define('TP_ALLOW_DEMO_DATA', true);
```

Reglas:

- El seed es idempotente.
- Rehace datos demo sin duplicarlos.
- Genera contrasenas aleatorias nuevas y las muestra una sola vez.
- No subir `dev/seed-test-data.php` en releases.

## Git

Estado:

```zsh
git status
git diff
```

Ultimos commits:

```zsh
git log --oneline -5
```

Nunca commitear:

- `CONTEXTO.md`
- `dev/`
- `release/`
- ZIPs
- tokens
- credenciales

## CSS y JS

Portal:

```text
assets/css/portal.css
assets/css/tatipilates-portal.min.css
assets/js/portal.js
```

Admin:

```text
assets/css/admin.css
```

Si se cambia CSS del portal, sincronizar minificado si aplica.

## PWA/cache

Si un cambio visual no aparece:

- recargar fuerte;
- revisar service worker;
- limpiar cache PWA;
- confirmar `filemtime()` cambia version de asset;
- reinstalar PWA si iOS conserva cache viejo.

## Flujo local recomendado

```text
Editar
-> probar LocalWP
-> git diff
-> actualizar CHANGELOG.md
-> pre-release
-> commit/push
-> publish staging release
```
