# Release runbook

Guia operativa para cerrar una tanda, publicarla en staging y promoverla a live.

## Principios

- `main` guarda codigo.
- `Publish staging release` publica una version candidata para staging.
- `Promote stable release` publica una version final para live.
- No editar staging/live manualmente sin replicar el cambio en Git.
- No promover a `stable` sin probar staging.

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

## Publicar release candidate a staging

En GitHub:

```text
Actions > Publish staging release > Run workflow
```

Usar version con sufijo `rc`:

```text
1.2.0-rc.1
```

El workflow:

- actualiza `tatipilates.php`;
- actualiza `updates.json` en `channels.staging`;
- crea tag `v1.2.0-rc.1`;
- genera ZIP;
- crea prerelease en GitHub.

Despues traer cambios a local:

```zsh
git pull --ff-only
```

## Actualizar staging

En WordPress staging:

```text
Escritorio > Actualizaciones > Comprobar de nuevo
Plugins > Tati Pilates > Actualizar ahora
```

Si no aparece:

- guardar de nuevo `Tati Pilates > Configuracion > Actualizaciones privadas`;
- confirmar canal `staging`;
- confirmar que el estado del token sea `Configurado por servidor`;
- confirmar `TP_GITHUB_TOKEN` y permiso `Contents: Read-only`;
- confirmar que GitHub release tiene asset ZIP.

## Probar staging

Usar:

```text
docs/testing-checklist.md
```

Si aparece bug:

1. Corregir en local.
2. Commit/push.
3. Publicar nuevo RC:

```text
1.2.0-rc.2
```

4. Repetir pruebas.

## Promover a stable

Cuando staging pasa:

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
5. Publicar `x.y.z-rc.1` a staging.
6. Probar solo el flujo afectado + smoke test basico.
7. Promover `x.y.z` a stable.

## Rollback

Si live falla despues de update:

1. No borrar tablas.
2. Reinstalar desde GitHub Release anterior si es urgente.
3. Crear hotfix en local.
4. Publicar nuevo patch.

El updater no hace rollback automatico.

## Comandos utiles

```zsh
git status
git log --oneline -5
gh workflow list --repo mariovicunadev/tatipilates-plugin
gh run list --repo mariovicunadev/tatipilates-plugin --limit 5
gh release list --repo mariovicunadev/tatipilates-plugin --limit 10
```
