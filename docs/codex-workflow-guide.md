# Guia de trabajo con Codex - Tati Pilates

Esta guia resume como abrir nuevos chats, como pedir cambios y que documentacion conviene mantener para que el proyecto escale sin perder orden.

## Estado actual de documentacion

El proyecto ya tiene:

- `README.md`: vision general, estructura, pre-release, updater privado y prompt base.
- `CHANGELOG.md`: historial de versiones y cambios relevantes.
- `docs/updater-workflow.md`: flujo de releases por canales `staging` y `stable`.
- `CONTEXTO.md`: memoria local sensible, ignorada por Git.

Esto ya cubre el flujo principal, pero para escalar conviene sumar documentacion especializada.

## Archivos .md recomendados

### Prioridad alta

1. `docs/testing-checklist.md`

Checklist de pruebas antes de publicar una version. Deberia cubrir:

- Login y reset de contrasena.
- Portal mobile.
- Reservas, cancelaciones y recuperaciones.
- Agenda semanal.
- Pagos y asistencia.
- Notificaciones.
- Updater privado.
- Roles: admin, Admin Pilates y alumna.

2. `docs/release-runbook.md`

Guia paso a paso para publicar:

- Como cerrar una tanda local.
- Como crear release candidate.
- Como actualizar staging.
- Como promover a stable.
- Que hacer si staging falla.
- Que hacer si live no detecta update.

3. `docs/security-checklist.md`

Checklist recurrente de seguridad:

- Nonces.
- Capabilities.
- Ownership checks.
- Sanitizacion y escaping.
- SQL prepare.
- Manejo de tokens.
- No subir credenciales.

4. `docs/data-model.md`

Resumen de tablas y relaciones:

- `tp_horarios`
- `tp_alumnas`
- `tp_reservas`
- `tp_recuperaciones`
- `tp_pagos`
- `tp_milestones`
- `tp_notificaciones`

Tambien deberia documentar indices, reglas de borrado y decisiones transaccionales.

### Prioridad media

5. `docs/architecture.md`

Mapa de clases y responsabilidades:

- `TP_Admin`
- `TP_Portal`
- `TP_Reservas`
- `TP_Alumnas`
- `TP_Notificaciones`
- `TP_Updater`
- `TP_Helpers`

Sirve para que nuevos chats o futuros colaboradores entiendan donde tocar.

6. `docs/local-development.md`

Instrucciones de LocalWP:

- Symlink del plugin.
- PHP de LocalWP.
- Como correr `php -l`.
- Como generar datos demo.
- Como resetear entorno local.

7. `docs/troubleshooting.md`

Problemas frecuentes:

- No llega email.
- No aparece update.
- GitHub token invalido.
- Staging no detecta release candidate.
- Live no detecta stable.
- Cache de WordPress updates.
- PWA/cache mostrando CSS viejo.

### Prioridad baja

8. `docs/product-decisions.md`

Decisiones funcionales importantes:

- Alumna inactiva cancela reservas futuras.
- Horario inactivo cancela reservas futuras.
- Eliminar alumna requiere no tener historial.
- `cupo_plan_semana()` es fuente central.
- Canal `staging` usa versiones `rc`.
- Canal `stable` usa versiones finales.

9. `docs/roadmap.md`

Lista viva de mejoras futuras.

## Prompt base para nuevos chats

Usar este prompt al abrir una nueva conversacion:

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

## Como pedir un cambio

Formato recomendado:

```text
Quiero cambiar:
[descripcion corta]

Comportamiento actual:
[que pasa ahora]

Comportamiento esperado:
[que deberia pasar]

Restricciones:
- No hagas commit todavia.
- Registralo en CHANGELOG.md.
- Mantene el cambio acotado.
```

## Workflow para cada tarea

1. Abrir chat nuevo si la tarea es grande o distinta a la tanda actual.
2. Pegar el prompt base.
3. Describir el cambio o bug.
4. Pedir que no se haga commit hasta revisar.
5. Probar en local o staging.
6. Cuando este listo, pedir:

```text
Listo para staging. Corre pre-release, commit, push y publica release candidate.
```

7. Codex deberia:

- correr validaciones;
- actualizar `CHANGELOG.md`;
- generar ZIP si corresponde;
- hacer commit y push;
- publicar `1.x.x-rc.1` al canal `staging`.

8. Probar staging.
9. Si todo esta bien, pedir:

```text
Promover 1.x.x a stable.
```

10. Live detectara la version final desde WordPress.

## Versionado recomendado

- Fix pequeno: subir patch. Ejemplo: `1.1.1` a `1.1.2`.
- Feature nueva: subir minor. Ejemplo: `1.1.1` a `1.2.0`.
- Cambio incompatible o migracion grande: subir major. Ejemplo: `1.1.1` a `2.0.0`.

Para staging usar siempre:

```text
1.2.0-rc.1
1.2.0-rc.2
```

Para live usar:

```text
1.2.0
```

## Reglas de oro

- No pegar tokens en chats.
- No subir `CONTEXTO.md`.
- No subir ZIPs de `release/`.
- No tocar staging/live manualmente sin replicarlo en Git.
- No promover a `stable` sin probar staging.
- No hacer commit hasta que la tanda este revisada.
- Mantener `CHANGELOG.md` al dia.
- Usar GitHub Actions para publicar y promover releases.

## Comandos utiles

```zsh
git status
git diff
./pre-release-check.sh --yes
git log --oneline -5
gh workflow list --repo wefefino/tatipilates-plugin
gh run list --repo wefefino/tatipilates-plugin --limit 5
```

## Flujo resumido

```text
Nuevo chat
-> prompt base
-> cambios locales
-> revisar
-> pre-release
-> commit/push
-> Publish staging release: 1.x.x-rc.1
-> probar staging
-> Promote stable release: 1.x.x
-> actualizar live desde WordPress
```
