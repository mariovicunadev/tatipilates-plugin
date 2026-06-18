# Updater privado por canales

El plugin soporta actualizaciones privadas desde GitHub Releases usando dos canales:

- `staging`: sitio de pruebas.
- `stable`: sitio live.

Cada instalacion de WordPress elige su canal desde:

```text
Tati Pilates > Configuracion > Actualizaciones privadas
```

## Que significa `rc`

`rc` significa `release candidate`.

Una version como:

```text
1.0.2-rc.1
```

significa: primera candidata de la version `1.0.2`.

Se usa para staging. Si pasa pruebas, se publica una version final:

```text
1.0.2
```

Esa version final es la que ve el sitio live en el canal `stable`.

## Configuracion en WordPress

En staging:

```text
Activar updater privado: si
Canal de updates: staging
GitHub token privado: token fine-grained
```

En live:

```text
Activar updater privado: si
Canal de updates: stable
GitHub token privado: token fine-grained
```

El token no se guarda en Git. Se guarda solo en la base de datos de WordPress.

Permiso minimo recomendado para el token:

```text
Repository access: only mariovicunadev/tatipilates-plugin
Permissions:
- Contents: Read-only
```

## Manifest

El archivo `updates.json` define que version ve cada canal.

Ejemplo:

```json
{
  "channels": {
    "staging": {
      "version": "1.0.2-rc.1",
      "tag": "v1.0.2-rc.1",
      "asset": "tatipilates-1.0.2-rc.1.zip"
    },
    "stable": {
      "version": "1.0.1",
      "tag": "v1.0.1",
      "asset": "tatipilates-1.0.1.zip"
    }
  }
}
```

El plugin lee ese manifest desde GitHub usando el token configurado.

## Publicar a staging

1. Terminar cambios en local.
2. Commit y push a `main`.
3. Ir a GitHub:

```text
Actions > Publish staging release > Run workflow
```

4. Usar una version `rc`, por ejemplo:

```text
1.0.2-rc.1
```

5. El workflow:

- actualiza `tatipilates.php` a `1.0.2-rc.1`;
- actualiza `updates.json` en el canal `staging`;
- crea tag `v1.0.2-rc.1`;
- genera `tatipilates-1.0.2-rc.1.zip`;
- crea un GitHub prerelease;
- sube el ZIP al release.

6. En WordPress staging:

```text
Plugins > Tati Pilates > Actualizar ahora
```

## Promover a live

Cuando staging pasa pruebas:

1. Ir a GitHub:

```text
Actions > Promote stable release > Run workflow
```

2. Usar version final, por ejemplo:

```text
1.0.2
```

3. El workflow:

- actualiza `tatipilates.php` a `1.0.2`;
- actualiza `updates.json` en el canal `stable`;
- crea tag `v1.0.2`;
- genera `tatipilates-1.0.2.zip`;
- crea un GitHub release normal;
- sube el ZIP al release.

4. En WordPress live:

```text
Plugins > Tati Pilates > Actualizar ahora
```

## Regla importante

`push` a `main` no publica automaticamente una version instalable.

Solo se publica cuando se corre uno de estos workflows:

- `Publish staging release`
- `Promote stable release`

Esto evita que live vea cambios no probados.

## Workflow resumido

```text
Local changes
-> commit/push main
-> GitHub Action: Publish staging release 1.0.2-rc.1
-> Staging actualiza desde WordPress
-> pruebas
-> GitHub Action: Promote stable release 1.0.2
-> Live actualiza desde WordPress
```
