# Release runbook

Guia operativa para cerrar una tanda local y promoverla directo a live.

Desde 2026-10-23 no hay staging: el repo esta enlazado a `devtatipilates.local`
mediante un directory junction (ver `docs/DEV-WINDOWS.md`), asi que la prueba
en local reemplaza la prueba en staging antes de promover a stable.

## Principios

- `main` guarda codigo.
- `Promote stable release` publica una version final para live.
- No editar live manualmente sin replicar el cambio en Git.
- No promover a `stable` sin probar en LocalWP (`devtatipilates.local`).
- El workflow `Publish staging release` y el canal `staging` de `updates.json`
  siguen existiendo por si hace falta una release candidate puntual, pero ya
  no son un paso obligatorio del flujo normal.

## Cerrar una tanda local

1. Revisar cambios:

```zsh
git status
git diff
```

2. Actualizar `docs/releases/changelog.json`, regenerar y verificar el changelog:

```zsh
php scripts/generate-changelog.php
php scripts/generate-changelog.php --check
```

No editar `CHANGELOG.md` manualmente.
3. Confirmar que la regresion se ejecuto en el WordPress local.
4. Correr pre-release local:

```zsh
./pre-release-check.sh --yes
```

El script detecta la version local y actualiza `release-metadata.json`. Revisar
y commitear ese cambio junto con la tanda.

5. Si pasa, commitear:

```zsh
git add .
git commit -m "tipo: descripcion corta"
git push
```

## Probar en local

Antes de promover, probar la tanda en `https://devtatipilates.local/` (enlazado
al repo via directory junction) usando:

```text
docs/testing-checklist.md
```

Si aparece un bug: corregir en local, commitear/pushear y volver a probar antes
de promover.

## Promover a stable

```text
Actions > Promote stable release > Run workflow
```

Usar version final:

```text
1.2.0
```

El workflow:

- actualiza `tatipilates.php`;
- actualiza `updates.json` en `channels.stable`;
- crea tag `v1.2.0`;
- genera ZIP;
- crea release final en GitHub.

Despues traer cambios a local:

```zsh
git pull --ff-only
```

## Actualizar live

En WordPress live:

```text
Escritorio > Actualizaciones > Comprobar de nuevo
Plugins > Tati Pilates > Actualizar ahora
```

Confirmar version instalada.

## Hotfix urgente

Para un fix pequeno:

1. Corregir en local.
2. Registrar la correccion en `docs/releases/changelog.json` y regenerar `CHANGELOG.md`.
3. Pre-release local.
4. Commit/push.
5. Probar el flujo afectado + smoke test basico en `devtatipilates.local`.
6. Promover `x.y.z` a stable.

## Rollback

Si live falla despues de update:

1. No borrar tablas.
2. Reinstalar desde GitHub Release anterior si es urgente.
3. Crear hotfix en local.
4. Publicar nuevo patch.

El updater no hace rollback automatico.

## Staging (opcional, ya no forma parte del flujo normal)

Si alguna vez se necesita una release candidate externa (ej. para que otra
persona pruebe sin usar LocalWP):

```text
Actions > Publish staging release > Run workflow
```

Version con sufijo `rc` (ej. `1.2.0-rc.1`). Actualiza `updates.json` en
`channels.staging`, crea tag y prerelease en GitHub. No es un requisito previo
a `Promote stable release`.

## Comandos utiles

```zsh
git status
git log --oneline -5
gh workflow list --repo mariovicunadev/tatipilates-plugin
gh run list --repo mariovicunadev/tatipilates-plugin --limit 5
gh release list --repo mariovicunadev/tatipilates-plugin --limit 10
```
